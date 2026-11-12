<?php

namespace App\Security\Voter;

use App\Entity\Announcement;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Qui peut modifier ou retirer une annonce.
 *
 * - EDIT : le propriétaire, tant que l'annonce est en ligne et disponible
 *   (on ne change pas le contenu d'une annonce réservée ou terminée) ;
 * - DEACTIVATE : le propriétaire ou un admin, sauf si l'annonce est réservée (échange en cours).
 *
 * @extends Voter<string, Announcement>
 */
final class AnnouncementVoter extends Voter
{
    public const EDIT = 'ANNOUNCEMENT_EDIT';
    public const DEACTIVATE = 'ANNOUNCEMENT_DEACTIVATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Announcement && \in_array($attribute, [self::EDIT, self::DEACTIVATE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Announcement $announcement */
        $announcement = $subject;
        $isOwner = $announcement->getUser()->getId() === $user->getId();
        $isOnline = null === $announcement->getInactivatedAt();
        $status = $announcement->getStatus();

        return match ($attribute) {
            self::EDIT => $isOwner && $isOnline && AnnouncementStatus::Available === $status,
            self::DEACTIVATE => ($isOwner || \in_array('ROLE_ADMIN', $user->getRoles(), true))
                && $isOnline
                && AnnouncementStatus::Reserved !== $status,
            default => false,
        };
    }
}
