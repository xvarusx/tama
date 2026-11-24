<?php

namespace App\Repository;

use App\Entity\Inventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Inventory>
 */
class InventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inventory::class);
    }

    public function findByProductId(Uuid|string $productId): ?Inventory
    {
        $uuid = $this->toUuid($productId);

        if ($uuid === null) {
            return null;
        }

        return $this->createQueryBuilder('i')
            ->andWhere('i.productId = :productId')
            ->setParameter('productId', $uuid, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActive(Uuid|string $id): ?Inventory
    {
        $uuid = $this->toUuid($id);

        if ($uuid === null) {
            return null;
        }

        return $this->findOneBy(['id' => $uuid, 'deletedAt' => null]);
    }

    public function findActiveByProductId(Uuid|string $productId): ?Inventory
    {
        $uuid = $this->toUuid($productId);

        if ($uuid === null) {
            return null;
        }

        return $this->createQueryBuilder('i')
            ->andWhere('i.productId = :productId')
            ->andWhere('i.deletedAt IS NULL')
            ->setParameter('productId', $uuid, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function toUuid(Uuid|string $id): ?Uuid
    {
        if ($id instanceof Uuid) {
            return $id;
        }

        return Uuid::isValid($id) ? Uuid::fromString($id) : null;
    }

    public function countActive(): int
    {
        return (int)$this->createQueryBuilder('i')
            ->where('i.deletedAt IS NULL')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Inventory[]
     */
    public function findActivePage(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.deletedAt IS NULL')
            ->orderBy('i.productId', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Inventory[]
     */
    public function findLowStock(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.quantityOnHand - i.quantityReserved <= i.reorderLevel')
            ->andWhere('i.deletedAt IS NULL')
            ->orderBy('i.quantityOnHand - i.quantityReserved', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Inventory[]
     */
    public function findOutOfStock(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.quantityOnHand - i.quantityReserved <= 0')
            ->andWhere('i.deletedAt IS NULL')
            ->orderBy('i.quantityOnHand - i.quantityReserved', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
