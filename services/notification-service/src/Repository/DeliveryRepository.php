<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\DeliveryFilter;
use App\Entity\Delivery;
use App\Enum\DeliveryStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Delivery>
 */
class DeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Delivery::class);
    }

    /**
     * @return array{0: Delivery[], 1: int}
     */
    public function search(DeliveryFilter $filter): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.notification', 'n');

        if ($filter->status !== null) {
            $qb->andWhere('d.status = :status')
                ->setParameter('status', $filter->status->value);
        }

        if ($filter->channel !== null) {
            $qb->andWhere('d.channel = :channel')
                ->setParameter('channel', $filter->channel->value);
        }

        if ($filter->type !== null) {
            $qb->andWhere('n.type = :type')
                ->setParameter('type', $filter->type);
        }

        if ($filter->search !== null) {
            $qb->andWhere('d.recipientEmail LIKE :search OR n.title LIKE :search OR d.subject LIKE :search')
                ->setParameter('search', '%' . $filter->search . '%');
        }

        $qb->orderBy('d.createdAt', 'DESC');
        $qb->setMaxResults($filter->pageSize)
            ->setFirstResult(($filter->page - 1) * $filter->pageSize);

        $results = $qb->getQuery()->getResult();

        // Count total items matching the filter
        $countQb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->leftJoin('d.notification', 'n');

        if ($filter->status !== null) {
            $countQb->andWhere('d.status = :status')
                ->setParameter('status', $filter->status->value);
        }

        if ($filter->channel !== null) {
            $countQb->andWhere('d.channel = :channel')
                ->setParameter('channel', $filter->channel->value);
        }

        if ($filter->type !== null) {
            $countQb->andWhere('n.type = :type')
                ->setParameter('type', $filter->type);
        }

        if ($filter->search !== null) {
            $countQb->andWhere('d.recipientEmail LIKE :search OR n.title LIKE :search OR d.subject LIKE :search')
                ->setParameter('search', '%' . $filter->search . '%');
        }

        $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

        return [$results, $totalItems];
    }

    /**
     * Rows claimed by a worker that no longer reports back.
     *
     * @return list<Delivery>
     */
    public function findStaleSending(DateTimeImmutable $before): array
    {
        /** @var list<Delivery> $rows */
        $rows = $this->createQueryBuilder('d')
            ->where('d.status = :status AND d.sendingAt < :before')
            ->setParameter('status', DeliveryStatus::SENDING->value)
            ->setParameter('before', $before, 'datetime_immutable')
            ->orderBy('d.sendingAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Due rows in the outbox queue, oldest first.
     *
     * @return list<Delivery>
     */
    public function findDue(int $limit, DateTimeImmutable $now): array
    {
        /** @var list<Delivery> $rows */
        $rows = $this->createQueryBuilder('d')
            ->where('d.status IN (:statuses) AND d.nextAttemptAt <= :now')
            ->setParameter('statuses', [DeliveryStatus::QUEUED->value, DeliveryStatus::RETRY_WAITING->value])
            ->setParameter('now', $now, 'datetime_immutable')
            ->orderBy('d.nextAttemptAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findWithAttempts(Uuid $id): ?Delivery
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.deliveryAttempts', 'a')
            ->where('d.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
