<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\User;
use App\Entity\Wishlist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wishlist>
 */
class WishlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wishlist::class);
    }

    /**
     * Wishlist d'un utilisateur : seulement les livres encore en ligne
     * (livre, annonce et vendeur actifs), les plus récents d'abord.
     *
     * @return Wishlist[]
     */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->innerJoin('w.book', 'b')->addSelect('b')
            ->innerJoin('b.announcement', 'a')->addSelect('a')
            ->innerJoin('a.user', 'seller')->addSelect('seller')
            ->andWhere('w.user = :user')
            ->andWhere('w.inactivatedAt IS NULL')
            ->andWhere('b.inactivatedAt IS NULL')
            ->andWhere('a.inactivatedAt IS NULL')
            ->andWhere('seller.inactivatedAt IS NULL')
            ->andWhere('seller.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('w.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ids des livres (parmi ceux donnés) que l'utilisateur a dans sa wishlist : sert à afficher les cœurs « pleins ».
     *
     * @param Book[] $books
     *
     * @return array<int, true> id du livre => true
     */
    public function findBookIdsForUser(User $user, array $books): array
    {
        if ([] === $books) {
            return [];
        }

        $rows = $this->createQueryBuilder('w')
            ->select('IDENTITY(w.book) AS id')
            ->andWhere('w.user = :user')
            ->andWhere('w.book IN (:books)')
            ->andWhere('w.inactivatedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('books', $books)
            ->getQuery()
            ->getArrayResult();

        return array_fill_keys(array_map('intval', array_column($rows, 'id')), true);
    }
}
