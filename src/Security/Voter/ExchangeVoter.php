<?php

namespace App\Security\Voter;

use App\Entity\Exchange;
use App\Entity\User;
use App\Enum\ExchangeStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Qui a le droit de faire quoi sur un échange.
 *
 * - le vendeur (propriétaire de l'annonce) accepte, refuse ou termine ;
 * - le demandeur peut annuler sa demande, le vendeur peut annuler un échange déjà accepté ;
 * - les deux participants voient la conversation et y écrivent tant qu'elle est ouverte.
 *
 * Usage : #[IsGranted(ExchangeVoter::ACCEPT, 'exchange')] ou is_granted('EXCHANGE_ACCEPT', exchange) dans Twig.
 *
 * @extends Voter<string, Exchange>
 */
final class ExchangeVoter extends Voter
{
    public const VIEW = 'EXCHANGE_VIEW';
    public const MESSAGE = 'EXCHANGE_MESSAGE';
    public const ACCEPT = 'EXCHANGE_ACCEPT';
    public const REFUSE = 'EXCHANGE_REFUSE';
    public const COMPLETE = 'EXCHANGE_COMPLETE';
    public const CANCEL = 'EXCHANGE_CANCEL';

    private const ATTRIBUTES = [self::VIEW, self::MESSAGE, self::ACCEPT, self::REFUSE, self::COMPLETE, self::CANCEL];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Exchange && \in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Exchange $exchange */
        $exchange = $subject;
        $isOwner = $exchange->getAnnouncement()->getUser()->getId() === $user->getId();
        $isRequester = $exchange->getRequester()->getId() === $user->getId();
        $status = $exchange->getStatus();

        return match ($attribute) {
            self::VIEW => $isOwner || $isRequester,
            self::MESSAGE => ($isOwner || $isRequester) && $status->isOpen(),
            self::ACCEPT, self::REFUSE => $isOwner && ExchangeStatus::Pending === $status,
            self::COMPLETE => $isOwner && ExchangeStatus::Accepted === $status,
            self::CANCEL => ($isRequester && $status->isOpen()) || ($isOwner && ExchangeStatus::Accepted === $status),
            default => false,
        };
    }
}
