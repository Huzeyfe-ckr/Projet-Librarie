<?php

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exchange>
 */
class ExchangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exchange::class);
    }

    /**
     * Échanges où l'utilisateur est demandeur ou propriétaire de l'annonce.
     *
     * @return Exchange[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.announcement', 'a')->addSelect('a')
            ->innerJoin('a.user', 'owner')->addSelect('owner')
            ->innerJoin('e.requester', 'r')->addSelect('r')
            ->andWhere('e.requester = :user OR a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.initiatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}