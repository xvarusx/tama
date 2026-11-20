<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\NotificationFilter;
use App\Entity\Notification;
use App\Enum\NotificationStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly EntityManagerInterface $em)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return array{0: Notification[], 1: int}
     */
    public function searchForRecipient(Uuid|string $recipientId, NotificationFilter $filter): array
    {
        $recipientId = self::toUuid($recipientId);

        $qb = $this->createQueryBuilder('n')
            ->where('n.recipientId = :recipientId AND n.visibleInApp = true')
            ->setParameter('recipientId', $recipientId, 'uuid');

        if ($filter->status !== null) {
            $qb->andWhere('n.status = :status')
                ->setParameter('status', $filter->status->value);
        }

        if ($filter->type !== null) {
            $qb->andWhere('n.type = :type')
                ->setParameter('type', $filter->type);
        }

        if ($filter->severity !== null) {
            $qb->andWhere('n.severity = :severity')
                ->setParameter('severity', $filter->severity->value);
        }

        if ($filter->search !== null) {
            $qb->andWhere('LOWER(n.title) LIKE :search OR LOWER(n.body) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($filter->search) . '%');
        }

        $qb->orderBy('n.createdAt', 'DESC');
        $qb->setMaxResults($filter->pageSize)
            ->setFirstResult(($filter->page - 1) * $filter->pageSize);

        $results = $qb->getQuery()->getResult();

        // Count total items matching the filter
        $countQb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipientId = :recipientId AND n.visibleInApp = true')
            ->setParameter('recipientId', $recipientId, 'uuid');

        if ($filter->status !== null) {
            $countQb->andWhere('n.status = :status')
                ->setParameter('status', $filter->status->value);
        }

        if ($filter->type !== null) {
            $countQb->andWhere('n.type = :type')
                ->setParameter('type', $filter->type);
        }

        if ($filter->severity !== null) {
            $countQb->andWhere('n.severity = :severity')
                ->setParameter('severity', $filter->severity->value);
        }

        if ($filter->search !== null) {
            $countQb->andWhere('LOWER(n.title) LIKE :search OR LOWER(n.body) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($filter->search) . '%');
        }

        $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

        return [$results, $totalItems];
    }

    /**
     * Business dedup guard for producers that reuse a key with a fresh event id.
     * The unique index is the concurrency guard; this check lets us skip a
     * duplicate recipient without failing the whole event.
     */
    public function hasDedupKey(Uuid|string $recipientId, string $dedupKey): bool
    {
        return (int)$this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipientId = :recipientId AND n.dedupKey = :dedupKey')
            ->setParameter('recipientId', self::toUuid($recipientId), 'uuid')
            ->setParameter('dedupKey', $dedupKey)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function countUnread(Uuid|string $recipientId): int
    {
        return (int)$this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipientId = :recipientId AND n.status = :status AND n.visibleInApp = true')
            ->setParameter('recipientId', self::toUuid($recipientId), 'uuid')
            ->setParameter('status', NotificationStatus::UNREAD->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findForRecipient(Uuid|string $recipientId, Uuid $id): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipientId = :recipientId AND n.id = :id')
            ->setParameter('recipientId', self::toUuid($recipientId), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function markAllRead(Uuid|string $recipientId): int
    {
        $qb = $this->em->createQueryBuilder();
        return (int)$qb->update(Notification::class, 'n')
            ->set('n.status', ':status')
            ->set('n.readAt', ':readAt')
            ->where('n.recipientId = :recipientId AND n.status = :oldStatus AND n.visibleInApp = true')
            ->setParameter('status', NotificationStatus::READ->value)
            ->setParameter('readAt', new DateTimeImmutable())
            ->setParameter('recipientId', self::toUuid($recipientId), 'uuid')
            ->setParameter('oldStatus', NotificationStatus::UNREAD->value)
            ->getQuery()
            ->execute();
    }

    public function archiveAllRead(Uuid|string $recipientId): int
    {
        $qb = $this->em->createQueryBuilder();
        return (int)$qb->update(Notification::class, 'n')
            ->set('n.status', ':status')
            ->set('n.archivedAt', ':archivedAt')
            ->where('n.recipientId = :recipientId AND n.status = :oldStatus AND n.visibleInApp = true')
            ->setParameter('status', NotificationStatus::ARCHIVED->value)
            ->setParameter('archivedAt', new DateTimeImmutable())
            ->setParameter('recipientId', self::toUuid($recipientId), 'uuid')
            ->setParameter('oldStatus', NotificationStatus::READ->value)
            ->getQuery()
            ->execute();
    }

    public function purgeArchivedBefore(DateTimeImmutable $before): int
    {
        // Remove through the ORM so the cascade deletes deliveries and attempts
        // on platforms where SQLite foreign keys are not enforced.
        $expired = $this->createQueryBuilder('n')
            ->where('n.status = :status AND n.archivedAt < :before')
            ->setParameter('status', NotificationStatus::ARCHIVED->value)
            ->setParameter('before', $before, 'datetime_immutable')
            ->getQuery()
            ->getResult();

        foreach ($expired as $notification) {
            $this->em->remove($notification);
        }
        if ($expired !== []) {
            $this->em->flush();
        }

        return \count($expired);
    }

    private static function toUuid(Uuid|string $recipientId): Uuid
    {
        return $recipientId instanceof Uuid ? $recipientId : Uuid::fromString($recipientId);
    }
}
