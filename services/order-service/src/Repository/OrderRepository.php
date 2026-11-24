<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\OrderType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByUuid(Uuid $uuid): ?Order
    {
        // uuid is now the primary key (id), so a direct find() keeps this alias working.
        return $this->find($uuid);
    }

    public function findByStatus(OrderStatus $status): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();
    }

    public function findByType(OrderType $type): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    public function findDrafts(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', OrderStatus::DRAFT)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByTypeAndStatus(OrderType $type, OrderStatus $status): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.type = :type')
            ->andWhere('o.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();
    }
}