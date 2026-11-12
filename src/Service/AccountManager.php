<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\ExchangeStatus;
use App\Repository\AnnouncementRepository;
use App\Repository\ExchangeRepository;
use App\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Gestion du compte par son propriétaire : avatar, mot de passe, suppression (RGPD).
 */
final class AccountManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageStorage $storage,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AnnouncementRepository $announcements,
        private readonly ExchangeRepository $exchanges,
        private readonly WishlistRepository $wishlists,
        private readonly ExchangeManager $exchangeManager,
        private readonly AnnouncementPublisher $publisher,
    ) {
    }

    /**
     * Enregistre le pseudo (déjà modifié par le formulaire) et l'avatar.
     */
    public function updateProfile(User $user, ?UploadedFile $avatar, bool $removeAvatar): void
    {
        if (null !== $avatar) {
            $user->setAvatarUrl($this->storage->store($avatar, 'avatars'));
        } elseif ($removeAvatar) {
            $user->setAvatarUrl(null);
        }

        $this->em->flush();
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->em->flush();
    }

    /**
     * Supprime le compte (droit à l'effacement, RGPD).
     *
     * On ne supprime pas la ligne (les échanges et messages de l'autre personne en dépendent) :
     * - les annonces en ligne sont retirées et les échanges en cours annulés ;
     * - les données personnelles sont effacées (email, pseudo, avatar, mot de passe) ;
     * - deletedAt empêche toute connexion (UserChecker) et masque le compte partout.
     */
    public function delete(User $user): void
    {
        // 1. Échanges en cours, côté demandeur comme côté vendeur
        foreach ($this->exchanges->findForUser($user) as $exchange) {
            if (!$exchange->getStatus()->isOpen()) {
                continue;
            }

            $receivedAndPending = $exchange->getAnnouncement()->getUser() === $user
                && ExchangeStatus::Pending === $exchange->getStatus();

            if ($receivedAndPending) {
                $this->exchangeManager->refuse($exchange, $user); // une demande reçue se refuse
            } else {
                $this->exchangeManager->cancel($exchange, $user);
            }
        }

        // 2. Annonces encore en ligne
        foreach ($this->announcements->findByOwner($user) as $announcement) {
            if (null === $announcement->getInactivatedAt()) {
                $this->publisher->deactivate($announcement, $user);
            }
        }

        // 3. Wishlist
        foreach ($this->wishlists->findBy(['user' => $user, 'inactivatedAt' => null]) as $item) {
            $item->setInactivatedAt(new \DateTimeImmutable());
        }

        // 4. Anonymisation : plus aucune donnée personnelle
        $id = $user->getId();
        $user
            ->setEmail(\sprintf('supprime-%d@bookshare.invalid', $id))
            ->setUsername(\sprintf('supprime-%d', $id))
            ->setAvatarUrl(null)
            ->setRoles([])
            ->setPassword('!') // aucun mot de passe ne peut correspondre à ce « hash »
            ->setDeletedAt(new \DateTimeImmutable());

        $this->em->flush();
    }
}
