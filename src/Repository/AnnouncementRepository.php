<?php

namespace App\Repository;

use App\Entity\Announcement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /**
     * Annonces visibles : annonce active ET propriétaire ni désactivé ni supprimé.
     */
    private function createActiveQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->addSelect('u')
            ->andWhere('a.inactivatedAt IS NULL')
            ->andWhere('u.inactivatedAt IS NULL')
            ->andWhere('u.deletedAt IS NULL');
    }

    /**
     * @return Announcement[]
     */
    public function findLatestActive(int $limit = 6): array
    {
        return $this->createActiveQueryBuilder()
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findActiveById(int $id): ?Announcement
    {
        return $this->createActiveQueryBuilder()
            ->andWhere('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActive(): int
    {
        return (int) $this->createActiveQueryBuilder()
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Paginator<Announcement>
     */
    public function searchActive(?string $term, int $page = 1, int $perPage = 12): Paginator
    {
        $qb = $this->createActiveQueryBuilder()
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (null !== $term && '' !== $term) {
            $qb->andWhere('a.title LIKE :term OR a.description LIKE :term')
                ->setParameter('term', '%' . $term . '%');
        }

        return new Paginator($qb, false);
    }
}