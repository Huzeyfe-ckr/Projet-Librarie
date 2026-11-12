<?php

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function findActiveByExchange(Exchange $exchange): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')->addSelect('s')
            ->andWhere('m.exchange = :exchange')
            ->andWhere('m.inactivatedAt IS NULL')
            ->setParameter('exchange', $exchange)
            ->orderBy('m.sentAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}