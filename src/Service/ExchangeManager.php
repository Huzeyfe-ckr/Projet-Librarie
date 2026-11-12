<?php

namespace App\Service;

use App\Entity\Announcement;
use App\Entity\Exchange;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\ExchangeStatus;
use App\Exception\ExchangeException;
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
 */
final class ExchangeManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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

        return $exchange;
    }

    public function sendMessage(Exchange $exchange, User $sender, string $content): void
    {
        if (!$exchange->getStatus()->isOpen()) {
            throw new ExchangeException('Cet échange est clos, tu ne peux plus y envoyer de message.');
        }

        $this->addMessage($exchange, $sender, trim($content));
        $this->em->flush();
    }

    /**
     * Le vendeur accepte : l'annonce est réservée et les autres demandes en attente sont refusées.
     */
    public function accept(Exchange $exchange, User $actor): void
    {
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
            }
        }

        $this->em->flush();
    }

    public function refuse(Exchange $exchange, User $actor): void
    {
        $this->assertStatus($exchange, [ExchangeStatus::Pending], 'Seule une demande en attente peut être refusée.');

        $exchange->setStatus(ExchangeStatus::Refused);
        $this->addMessage($exchange, $actor, '✕ Demande refusée.');
        $this->em->flush();
    }

    /**
     * Le livre a été remis : l'échange et l'annonce sont terminés.
     */
    public function complete(Exchange $exchange, User $actor): void
    {
        $this->assertStatus($exchange, [ExchangeStatus::Accepted], 'Seul un échange accepté peut être terminé.');

        $exchange
            ->setStatus(ExchangeStatus::Completed)
            ->setCompletedAt(new \DateTimeImmutable());
        $exchange->getAnnouncement()->setStatus(AnnouncementStatus::Completed);
        $this->addMessage($exchange, $actor, '★ Échange terminé. Merci !');

        $this->em->flush();
    }

    /**
     * Annulation : si l'échange était accepté, l'annonce redevient disponible pour les autres.
     */
    public function cancel(Exchange $exchange, User $actor): void
    {
        $this->assertStatus($exchange, [ExchangeStatus::Pending, ExchangeStatus::Accepted], 'Cet échange ne peut plus être annulé.');

        if (ExchangeStatus::Accepted === $exchange->getStatus()) {
            $exchange->getAnnouncement()->setStatus(AnnouncementStatus::Available);
        }

        $exchange->setStatus(ExchangeStatus::Cancelled);
        $this->addMessage($exchange, $actor, '✕ Échange annulé.');
        $this->em->flush();
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
