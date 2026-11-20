<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvoicePayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<InvoicePayment> */
final class InvoicePaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoicePayment::class);
    }

    public function findByPaymentId(Uuid $paymentId): ?InvoicePayment
    {
        return $this->findOneBy(['paymentId' => $paymentId]);
    }
}
