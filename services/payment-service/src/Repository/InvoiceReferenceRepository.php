<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvoiceReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceReference>
 */
class InvoiceReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceReference::class);
    }

    public function findActiveById(string $id): ?InvoiceReference
    {
        return $this->find($id);
    }

    public function findByNumber(string $number): ?InvoiceReference
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.number = :number')
            ->setParameter('number', $number)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->setParameter('status', $status)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOutstanding(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.outstanding > 0')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}