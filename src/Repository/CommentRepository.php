<?php

namespace App\Repository;

use App\Entity\Announcement;
use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Avis visibles : commentaire actif ET auteur non désactivé.
     *
     * @return Comment[]
     */
    public function findActiveByAnnouncement(Announcement $announcement): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')->addSelect('u')
            ->andWhere('c.announcement = :announcement')
            ->andWhere('c.inactivatedAt IS NULL')
            ->andWhere('u.inactivatedAt IS NULL')
            ->setParameter('announcement', $announcement)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
