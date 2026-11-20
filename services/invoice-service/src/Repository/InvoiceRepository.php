<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Invoice> */
final class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findByUuid(Uuid|string $id): ?Invoice
    {
        try {
            $uuid = $id instanceof Uuid ? $id : Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->find($uuid);
    }

    public function findActiveByOrderId(Uuid $orderId): ?Invoice
    {
        return $this->findOneBy(['activeOrderKey' => $orderId->toRfc4122()]);
    }

    /**
     * @return array{0: list<Invoice>, 1: int}
     */
    public function search(
        int $page,
        int $pageSize,
        ?InvoiceStatus $status = null,
        ?InvoiceType $type = null,
        ?Uuid $orderId = null,
        ?Uuid $partyId = null,
        ?string $search = null,
        ?bool $overdue = null,
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        if ($status !== null) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if ($type !== null) {
            $qb->andWhere('i.type = :type')->setParameter('type', $type);
        }
        if ($orderId !== null) {
            $qb->andWhere('i.orderId = :orderId')->setParameter('orderId', $orderId, 'uuid');
        }
        if ($partyId !== null) {
            $qb->andWhere('i.partyId = :partyId')->setParameter('partyId', $partyId, 'uuid');
        }
        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(i.number) LIKE :q OR LOWER(i.partyName) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(trim($search)) . '%');
        }
        if ($overdue !== null) {
            $qb->andWhere('i.dueDate IS NOT NULL')
                ->andWhere('i.status IN (:overdueStatuses)')
                ->setParameter('overdueStatuses', [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID]);

            $operator = $overdue ? '<' : '>=';
            $qb->andWhere(sprintf('i.dueDate %s :today', $operator))
                ->setParameter('today', new DateTimeImmutable('today'));
        }

        $paginator = new Paginator($qb->getQuery());

        return [array_values(iterator_to_array($paginator->getIterator())), $paginator->count()];
    }

    public function highestNumberFor(InvoiceType $type, int $year): ?string
    {
        /** @var array{number: string}|null $row */
        $row = $this->createQueryBuilder('i')
            ->select('i.number')
            ->andWhere('i.number LIKE :prefix')
            ->setParameter('prefix', sprintf('%s-%d-%%', $type->numberPrefix(), $year))
            ->orderBy('LENGTH(i.number)', 'DESC')
            ->addOrderBy('i.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row['number'] ?? null;
    }
}
