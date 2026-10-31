<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InventoryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private InventoryRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->repository = $this->em->getRepository(Inventory::class);
        $this->em->getConnection()->executeStatement('DELETE FROM inventory_movement');
        $this->em->getConnection()->executeStatement('DELETE FROM inventory');
        $this->em->clear();
    }

    public function testFindByProductId(): void
    {
        $this->seed(2, '100.000', '10.000', '20.000');

        self::assertNotNull($this->repository->findByProductId(2));
        self::assertNull($this->repository->findByProductId(99));
    }

    public function testActiveLookupsExcludeSoftDeletedRows(): void
    {
        $active = $this->seed(1, '100.000', '10.000', '20.000');
        $deleted = $this->seed(2, '0.000', '0.000', '0.000', new \DateTimeImmutable());
        $this->seed(3, '200.000', '0.000', '20.000');

        self::assertNotNull($this->repository->findActive($active));
        self::assertNull($this->repository->findActive($deleted));
        self::assertSame(1, $this->repository->findActiveByProductId(1)?->getProductId());
        self::assertNull($this->repository->findActiveByProductId(2));
        self::assertSame(
            [1, 3],
            array_map(
                static fn (Inventory $i): ?int => $i->getProductId(),
                $this->repository->findActivePage(10, 0),
            ),
        );
        self::assertSame([], array_map(
            static fn (Inventory $i): ?int => $i->getProductId(),
            $this->repository->findLowStock(),
        ));
        self::assertSame([], array_map(
            static fn (Inventory $i): ?int => $i->getProductId(),
            $this->repository->findOutOfStock(),
        ));
    }

    public function testFindLowStockIncludesOutOfStockOrderedByAvailable(): void
    {
        $this->seed(1, '10.000', '2.000', '10.000'); // available 8
        $this->seed(2, '100.000', '10.000', '20.000'); // available 90
        $this->seed(3, '5.000', '5.000', '0.000'); // available 0

        self::assertSame([3, 1], array_map(
            static fn (Inventory $i): ?int => $i->getProductId(),
            $this->repository->findLowStock()
        ));
    }

    public function testFindOutOfStock(): void
    {
        $this->seed(1, '10.000', '2.000', '10.000');
        $this->seed(3, '5.000', '5.000', '0.000');

        self::assertSame([3], array_map(
            static fn (Inventory $i): ?int => $i->getProductId(),
            $this->repository->findOutOfStock()
        ));
    }

    private function seed(int $productId, string $onHand, string $reserved, string $reorder, ?\DateTimeImmutable $deletedAt = null): int
    {
        $now = new \DateTimeImmutable();
        $inventory = (new Inventory())
            ->setProductId($productId)
            ->setQuantityOnHand($onHand)
            ->setQuantityReserved($reserved)
            ->setReorderLevel($reorder)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setDeletedAt($deletedAt);

        $this->em->persist($inventory);
        $this->em->flush();
        $id = $inventory->getId();
        $this->em->clear();

        return $id;
    }
}
