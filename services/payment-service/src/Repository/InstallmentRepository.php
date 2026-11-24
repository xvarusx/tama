<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Installment;
use App\Enum\InstallmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Installment>
 */
class InstallmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Installment::class);
    }

    public function findActiveById(string $id): ?Installment
    {
        return $this->find($id);
    }

    public function findUnpaidByPaymentPlan(string $paymentPlanId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.paymentPlan = :paymentPlanId')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('paymentPlanId', $paymentPlanId)
            ->setParameter('statuses', [InstallmentStatus::UNPAID, InstallmentStatus::PARTIALLY_PAID])
            ->orderBy('i.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOverdue(\DateTimeInterface $today): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.dueDate < :today')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('today', $today)
            ->setParameter('statuses', [InstallmentStatus::UNPAID, InstallmentStatus::PARTIALLY_PAID])
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingReminders(\DateTimeInterface $today): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.dueDate <= :today')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('today', $today)
            ->setParameter('statuses', [InstallmentStatus::UNPAID, InstallmentStatus::PARTIALLY_PAID])
            ->getQuery()
            ->getResult();
    }

    public function findOldestUnpaid(): ?Installment
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.paidAmount < i.amount')
            ->setParameter('statuses', [InstallmentStatus::UNPAID, InstallmentStatus::PARTIALLY_PAID])
            ->orderBy('i.paymentPlan', 'ASC')
            ->addOrderBy('i.sequence', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}