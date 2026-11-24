<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentPlan>
 */
class PaymentPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentPlan::class);
    }

    public function findActiveById(string $id): ?PaymentPlan
    {
        return $this->find($id);
    }

    public function findByInvoice(string $invoiceId): ?PaymentPlan
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.invoiceId = :invoiceId')
            ->setParameter('invoiceId', $invoiceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}