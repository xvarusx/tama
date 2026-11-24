<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvoiceReference;
use App\Entity\Payment;
use App\Entity\Installment;
use App\Enum\InstallmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<\App\Entity\PaymentAllocation>
 */
class PaymentAllocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, \App\Entity\PaymentAllocation::class);
    }

    /**
     * @param Payment $payment
     * @return \App\Entity\PaymentAllocation[]
     */
    public function findByPayment(Payment $payment): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.payment = :payment')
            ->setParameter('payment', $payment)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Installment $installment
     * @return \App\Entity\PaymentAllocation[]
     */
    public function findByInstallment(Installment $installment): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.installment = :installment')
            ->setParameter('installment', $installment)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param InvoiceReference $invoice
     * @return \App\Entity\PaymentAllocation[]
     */
    public function findByInvoice(InvoiceReference $invoice): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function totalAllocatedToInstallment(Installment $installment): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('SUM(a.amount)')
            ->andWhere('a.installment = :installment')
            ->setParameter('installment', $installment)
            ->getQuery()
            ->getSingleScalarResult();
    }
}