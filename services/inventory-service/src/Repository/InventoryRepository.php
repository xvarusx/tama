<?php

namespace App\Repository;

use App\Entity\Inventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inventory>
 */
class InventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inventory::class);
    }

    public function findByProductId(int $productId): ?Inventory
    {
        return $this->findOneBy(['productId' => $productId]);
    }

    public function findActive(int $id): ?Inventory
    {
        return $this->findOneBy(['id' => $id, 'deletedAt' => null]);
    }

    public function findActiveByProductId(int $productId): ?Inventory
    {
        return $this->findOneBy(['productId' => $productId, 'deletedAt' => null]);
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
