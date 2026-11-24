<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OutboxEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OutboxEvent>
 */
class OutboxEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OutboxEvent::class);
    }

    /**
     * @return OutboxEvent[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.publishedAt IS NULL')
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.publishedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}