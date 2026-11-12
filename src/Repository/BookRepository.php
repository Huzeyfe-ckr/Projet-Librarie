<?php

namespace App\Repository;

use App\Entity\Announcement;
use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * Livres actifs groupés par id d'annonce.
     *
     * @param Announcement[] $announcements
     *
     * @return array<int, Book[]>
     */
    public function findActiveByAnnouncements(array $announcements): array
    {
        if ([] === $announcements) {
            return [];
        }

        $grouped = [];
        foreach ($this->findBy(['announcement' => $announcements, 'inactivatedAt' => null]) as $book) {
            $grouped[$book->getAnnouncement()->getId()][] = $book;
        }

        return $grouped;
    }

    /**
     * Genres des livres en ligne, pour la liste déroulante des filtres.
     *
     * @return string[]
     */
    public function findGenres(): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('DISTINCT b.genre')
            ->innerJoin('b.announcement', 'a')
            ->andWhere('b.inactivatedAt IS NULL')
            ->andWhere('a.inactivatedAt IS NULL')
            ->andWhere('b.genre IS NOT NULL')
            ->andWhere("b.genre <> ''")
            ->orderBy('b.genre', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_unique(array_map('trim', $rows)));
    }
}
