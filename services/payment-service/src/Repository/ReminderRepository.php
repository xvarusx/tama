<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reminder;
use App\Enum\ReminderKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reminder>
 */
class ReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reminder::class);
    }

    public function findActiveById(string $id): ?Reminder
    {
        return $this->find($id);
    }

    public function findPendingByKind(ReminderKind $kind): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.kind = :kind')
            ->andWhere('r.status = :pending')
            ->setParameter('kind', $kind)
            ->setParameter('pending', 'PENDING')
            ->orderBy('r.scheduledFor', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDueBefore(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.scheduledFor <= :date')
            ->andWhere('r.status = :pending')
            ->setParameter('date', $date)
            ->setParameter('pending', 'PENDING')
            ->getQuery()
            ->getResult();
    }
}