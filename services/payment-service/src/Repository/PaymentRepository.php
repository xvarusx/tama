<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use App\Enum\PaymentDirection;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findActiveById(string $id): ?Payment
    {
        return $this->find($id);
    }

    public function findByStatus(PaymentStatus $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param PaymentStatus|null $status
     * @param PaymentMethod|null $method
     * @param string|null $partyId
     * @param string|null $search
     * @return array{0: list<Payment>, 1: int}
     */
    public function search(
        ?int $page = null,
        ?int $pageSize = null,
        ?PaymentStatus $status = null,
        ?PaymentMethod $method = null,
        ?string $partyId = null,
        ?string $search = null,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC');

        if ($page !== null && $pageSize !== null) {
            $qb->setFirstResult(($page - 1) * $pageSize)
                ->setMaxResults($pageSize);
        }

        if ($status !== null) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }
        if ($method !== null) {
            $qb->andWhere('p.method = :method')->setParameter('method', $method);
        }
        if ($partyId !== null) {
            $qb->andWhere('p.partyId = :partyId')->setParameter('partyId', $partyId);
        }
        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(p.number) LIKE :q OR LOWER(p.partyName) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(trim($search)) . '%');
        }

        $paginator = new Paginator($qb->getQuery());

        return [array_values(iterator_to_array($paginator->getIterator())), $paginator->count()];
    }

    public function findByParty(string $partyId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.partyId = :partyId')
            ->setParameter('partyId', $partyId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByDirection(PaymentDirection $direction): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.direction = :direction')
            ->setParameter('direction', $direction)
            ->orderBy('p.paidAt DESC NULLS LAST', 'p.createdAt DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByMethod(PaymentMethod $method): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.method = :method')
            ->setParameter('method', $method)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}