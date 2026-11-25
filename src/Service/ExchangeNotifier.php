<?php

namespace App\Service;

use App\Entity\Exchange;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Prévient par email l'autre participant d'un échange : nouvelle demande, réponse du vendeur,
 * nouveau message… Appelé par ExchangeManager, une fois les changements enregistrés en base.
 *
 * En dev, les emails arrivent dans Mailpit : http://localhost:8025.
 */
final class ExchangeNotifier
{
    public const REQUESTED = 'requested';
    public const ACCEPTED = 'accepted';
    public const REFUSED = 'refused';
    public const RESERVED_FOR_SOMEONE_ELSE = 'reserved_for_someone_else';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const MESSAGE = 'message';

    /** Objet de l'email pour chaque événement (%s = titre de l'annonce). */
    private const SUBJECTS = [
        self::REQUESTED => 'Nouvelle demande pour « %s »',
        self::ACCEPTED => 'Ta demande pour « %s » est acceptée',
        self::REFUSED => 'Ta demande pour « %s » n\'a pas été retenue',
        self::RESERVED_FOR_SOMEONE_ELSE => '« %s » a été réservé par quelqu\'un d\'autre',
        self::COMPLETED => 'Échange terminé pour « %s »',
        self::CANCELLED => 'Échange annulé pour « %s »',
        self::MESSAGE => 'Nouveau message à propos de « %s »',
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.mailer_from%')]
        private readonly string $from,
    ) {
    }

    /**
     * @param User        $actor   la personne qui vient d'agir : l'email part vers l'AUTRE participant
     * @param string|null $excerpt texte du message, pour une demande ou un nouveau message
     */
    public function notify(Exchange $exchange, User $actor, string $event, ?string $excerpt = null): void
    {
        $recipient = $this->otherParticipant($exchange, $actor);

        // Compte supprimé ou désactivé : son adresse n'est plus valable, on n'écrit pas
        if (null !== $recipient->getDeletedAt() || null !== $recipient->getInactivatedAt()) {
            return;
        }

        $announcement = $exchange->getAnnouncement();

        $email = (new TemplatedEmail())
            ->from(new Address($this->from, 'BookShare'))
            ->to(new Address((string) $recipient->getEmail(), (string) $recipient->getUsername()))
            ->subject(\sprintf(self::SUBJECTS[$event], $announcement->getTitle()).' · BookShare')
            ->htmlTemplate('emails/exchange_notification.html.twig')
            ->context([
                'event' => $event,
                'recipient' => $recipient,
                'actor' => $actor,
                'exchange' => $exchange,
                'announcement' => $announcement,
                'excerpt' => null !== $excerpt ? mb_strimwidth(trim($excerpt), 0, 300, '…') : null,
            ]);

        // Un email qui ne part pas ne doit pas annuler l'action (déjà enregistrée) : on le note dans les logs
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Notification d\'échange non envoyée : {error}', [
                'error' => $e->getMessage(),
                'exchange' => $exchange->getId(),
                'event' => $event,
            ]);
        }
    }

    private function otherParticipant(Exchange $exchange, User $actor): User
    {
        $owner = $exchange->getAnnouncement()->getUser();

        return $owner->getId() === $actor->getId() ? $exchange->getRequester() : $owner;
    }
}
