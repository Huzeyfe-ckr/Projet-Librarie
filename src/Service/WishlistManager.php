<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\User;
use App\Entity\Wishlist;
use App\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ajoute ou retire un livre de la wishlist d'un utilisateur.
 *
 * Comme partout dans l'app, on ne supprime pas la ligne : on la désactive (inactivatedAt),
 * et on la réactive si le livre est de nouveau ajouté (la contrainte d'unicité user + book l'impose).
 */
final class WishlistManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WishlistRepository $wishlists,
    ) {
    }

    /**
     * @return bool true si le livre est maintenant dans la wishlist, false s'il vient d'en être retiré
     */
    public function toggle(User $user, Book $book): bool
    {
        if ($book->getAnnouncement()->getUser()->getId() === $user->getId()) {
            throw new \DomainException('Tu ne peux pas ajouter ton propre livre à ta wishlist.');
        }

        $item = $this->wishlists->findOneBy(['user' => $user, 'book' => $book]);

        if (null === $item) {
            $item = (new Wishlist())->setUser($user)->setBook($book);
            $this->em->persist($item);
            $added = true;
        } elseif (null === $item->getInactivatedAt()) {
            $item->setInactivatedAt(new \DateTimeImmutable());
            $added = false;
        } else {
            $item->setInactivatedAt(null)->setAddedAt(new \DateTimeImmutable());
            $added = true;
        }

        $this->em->flush();

        return $added;
    }
}
