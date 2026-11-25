<?php

namespace App\Service;

use App\Entity\Announcement;
use App\Entity\Exchange;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\ExchangeStatus;
use App\Exception\ExchangeException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Toute la logique métier des échanges : création d'une demande et changements de statut.
 *
 * Cycle de vie :
 *
 *   pending ──accept()──▶ accepted ──complete()──▶ completed
 *      │                      │
 *      ├──refuse()──▶ refused  └──cancel()──▶ cancelled (l'annonce redevient disponible)
 *      └──cancel()──▶ cancelled
 *
 * Le statut de l'annonce suit : available → reserved (accept) → completed (complete).
 *
 * Les droits (qui peut faire quoi) sont vérifiés par ExchangeVoter dans le contrôleur ;
 * ce service vérifie seulement que la transition est possible.
 *
 * Concurrence : chaque transition se fait dans une transaction qui verrouille l'annonce
 * (voir transition()), pour que deux clics simultanés ne puissent pas réserver deux fois la même annonce.
 *
 * Emails : l'autre participant est prévenu (ExchangeNotifier) APRÈS l'enregistrement en base,
 * pour ne jamais annoncer un changement qui aurait finalement échoué.
 */
final class ExchangeManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExchangeNotifier $notifier,
    ) {
    }

    /**
     * Crée une demande d'échange sur une annonce, avec le premier message.
     */
    public function request(Announcement $announcement, User $requester, string $text): Exchange
    {
        if ($announcement->getUser()->getId() === $requester->getId()) {
            throw new ExchangeException('C\'est ta propre annonce.');
        }
        if (AnnouncementStatus::Available !== $announcement->getStatus()) {
            throw new ExchangeException('Cette annonce n\'est plus disponible.');
        }

        $text = trim($text);
        if ('' === $text) {
            $text = \sprintf('Bonjour, ton annonce « %s » m\'intéresse. Est-elle toujours disponible ?', $announcement->getTitle());
        }

        $exchange = (new Exchange())
            ->setAnnouncement($announcement)
            ->setRequester($requester);

        $this->em->persist($exchange);
        $this->addMessage($exchange, $requester, $text);
        $this->em->flush();

        $this->notifier->notify($exchange, $requester, ExchangeNotifier::REQUESTED, $text);

        return $exchange;
    }

    public function sendMessage(Exchange $exchange, User $sender, string $content): void
    {
        if (!$exchange->getStatus()->isOpen()) {
            throw new ExchangeException('Cet échange est clos, tu ne peux plus y envoyer de message.');
        }

        // Anti-spam : un email seulement quand la personne « prend la parole » (le message précédent
        // venait de l'autre). Trois messages d'affilée = un seul email.
        $previous = $this->lastMessage($exchange);
        $startsNewTurn = null === $previous || $previous->getSender()->getId() !== $sender->getId();

        $this->addMessage($exchange, $sender, trim($content));
        $this->em->flush();

        if ($startsNewTurn) {
            $this->notifier->notify($exchange, $sender, ExchangeNotifier::MESSAGE, $content);
        }
    }

    /**
     * Le vendeur accepte : l'annonce est réservée et les autres demandes en attente sont refusées.
     */
    public function accept(Exchange $exchange, User $actor): void
    {
        /** @var Exchange[] $refusedOthers demandes refusées automatiquement, à prévenir après coup */
        $refusedOthers = [];

        $this->transition($exchange, function () use ($exchange, $actor, &$refusedOthers): void {
            $this->assertStatus($exchange, [ExchangeStatus::Pending], 'Seule une demande en attente peut être acceptée.');

            $announcement = $exchange->getAnnouncement();
            if (AnnouncementStatus::Available !== $announcement->getStatus()) {
                throw new ExchangeException('Cette annonce est déjà réservée pour un autre échange.');
            }

            $exchange->setStatus(ExchangeStatus::Accepted);
            $announcement->setStatus(AnnouncementStatus::Reserved);
            $this->addMessage($exchange, $actor, '✓ Demande acceptée. Organisez la remise du livre ici.');

            foreach ($announcement->getExchanges() as $other) {
                if ($other !== $exchange && ExchangeStatus::Pending === $other->getStatus()) {
                    $other->setStatus(ExchangeStatus::Refused);
                    $this->addMessage($other, $actor, 'Désolé, cette annonce a été réservée pour quelqu\'un d\'autre.');
                    $refusedOthers[] = $other;
                }
            }
        });

        $this->notifier->notify($exchange, $actor, ExchangeNotifier::ACCEPTED);
        foreach ($refusedOthers as $other) {
            $this->notifier->notify($other, $actor, ExchangeNotifier::RESERVED_FOR_SOMEONE_ELSE);
        }
    }

    public function refuse(Exchange $exchange, User $actor): void
    {
        $this->transition($exchange, function () use ($exchange, $actor): void {
            $this->assertStatus($exchange, [ExchangeStatus::Pending], 'Seule une demande en attente peut être refusée.');

            $exchange->setStatus(ExchangeStatus::Refused);
            $this->addMessage($exchange, $actor, '✕ Demande refusée.');
        });

        $this->notifier->notify($exchange, $actor, ExchangeNotifier::REFUSED);
    }

    /**
     * Le livre a été remis : l'échange et l'annonce sont terminés.
     */
    public function complete(Exchange $exchange, User $actor): void
    {
        $this->transition($exchange, function () use ($exchange, $actor): void {
            $this->assertStatus($exchange, [ExchangeStatus::Accepted], 'Seul un échange accepté peut être terminé.');

            $exchange
                ->setStatus(ExchangeStatus::Completed)
                ->setCompletedAt(new \DateTimeImmutable());
            $exchange->getAnnouncement()->setStatus(AnnouncementStatus::Completed);
            $this->addMessage($exchange, $actor, '★ Échange terminé. Merci !');
        });

        $this->notifier->notify($exchange, $actor, ExchangeNotifier::COMPLETED);
    }

    /**
     * Annulation : si l'échange était accepté, l'annonce redevient disponible pour les autres.
     */
    public function cancel(Exchange $exchange, User $actor): void
    {
        $this->transition($exchange, function () use ($exchange, $actor): void {
            $this->assertStatus($exchange, [ExchangeStatus::Pending, ExchangeStatus::Accepted], 'Cet échange ne peut plus être annulé.');

            if (ExchangeStatus::Accepted === $exchange->getStatus()) {
                $exchange->getAnnouncement()->setStatus(AnnouncementStatus::Available);
            }

            $exchange->setStatus(ExchangeStatus::Cancelled);
            $this->addMessage($exchange, $actor, '✕ Échange annulé.');
        });

        $this->notifier->notify($exchange, $actor, ExchangeNotifier::CANCELLED);
    }

    /**
     * Exécute un changement de statut à l'abri des requêtes simultanées.
     *
     * 1. On ouvre une transaction.
     * 2. On relit l'annonce avec un verrou (SELECT … FOR UPDATE) : une deuxième requête qui veut
     *    toucher à la même annonce attend ici que la première ait fini (commit ou rollback).
     * 3. On relit aussi l'échange : son statut a pu changer pendant l'attente (ex. déjà refusé).
     * 4. Les vérifications (assertStatus…) se font donc sur des données à jour, puis on enregistre.
     *
     * On verrouille toujours l'annonce avant l'échange, dans le même ordre partout, pour éviter
     * que deux requêtes s'attendent l'une l'autre (deadlock).
     *
     * Pas de wrapInTransaction() : il fermerait l'EntityManager à chaque ExchangeException
     * (une simple erreur métier comme « déjà réservée »), et la suite de la requête planterait.
     *
     * @param callable(): void $change
     */
    private function transition(Exchange $exchange, callable $change): void
    {
        $this->em->beginTransaction();

        try {
            $this->em->refresh($exchange->getAnnouncement(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($exchange, LockMode::PESSIMISTIC_WRITE);

            $change();

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();

            throw $e;
        }
    }

    /**
     * @param ExchangeStatus[] $allowed
     */
    private function assertStatus(Exchange $exchange, array $allowed, string $error): void
    {
        if (!\in_array($exchange->getStatus(), $allowed, true)) {
            throw new ExchangeException($error);
        }
    }

    /**
     * Dernier message de la conversation (le plus grand id = le plus récent).
     */
    private function lastMessage(Exchange $exchange): ?Message
    {
        $last = null;
        foreach ($exchange->getMessages() as $message) {
            if (null === $last || $message->getId() > $last->getId()) {
                $last = $message;
            }
        }

        return $last;
    }

    private function addMessage(Exchange $exchange, User $sender, string $content): void
    {
        $message = (new Message())
            ->setSender($sender)
            ->setContent($content);

        // addMessage() met à jour les deux côtés de la relation
        $exchange->addMessage($message);
        $this->em->persist($message);
    }
}
