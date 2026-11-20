<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use App\Tests\Support\ProductUuidTrait;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class InventoryRepositoryTest extends KernelTestCase
{
    use ProductUuidTrait;

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
        $this->seed($this->productUuid(2), '100.000', '10.000', '20.000');

        self::assertNotNull($this->repository->findByProductId($this->productUuid(2)));
        self::assertNull($this->repository->findByProductId($this->productUuid(99)));
    }

    public function testActiveLookupsExcludeSoftDeletedRows(): void
    {
        $active = $this->seed($this->productUuid(1), '100.000', '10.000', '20.000');
        $deleted = $this->seed($this->productUuid(2), '0.000', '0.000', '0.000', new DateTimeImmutable());
        $this->seed($this->productUuid(3), '200.000', '0.000', '20.000');

        self::assertNotNull($this->repository->findActive($active));
        self::assertNull($this->repository->findActive($deleted));
        self::assertSame(
            $this->productUuid(1),
            $this->repository->findActiveByProductId($this->productUuid(1))?->getProductId()?->toRfc4122(),
        );
        self::assertNull($this->repository->findActiveByProductId($this->productUuid(2)));

        $expectedActive = [$this->productUuid(1), $this->productUuid(3)];
        sort($expectedActive);
        self::assertSame($expectedActive, array_map(
            static fn(Inventory $i): ?string => $i->getProductId()?->toRfc4122(),
            $this->repository->findActivePage(10, 0),
        ));
        self::assertSame([], array_map(
            static fn(Inventory $i): ?string => $i->getProductId()?->toRfc4122(),
            $this->repository->findLowStock(),
        ));
        self::assertSame([], array_map(
            static fn(Inventory $i): ?string => $i->getProductId()?->toRfc4122(),
            $this->repository->findOutOfStock(),
        ));
    }

    public function testFindLowStockIncludesOutOfStockOrderedByAvailable(): void
    {
        $this->seed($this->productUuid(1), '10.000', '2.000', '10.000'); // available 8
        $this->seed($this->productUuid(2), '100.000', '10.000', '20.000'); // available 90
        $this->seed($this->productUuid(3), '5.000', '5.000', '0.000'); // available 0

        self::assertSame([$this->productUuid(3), $this->productUuid(1)], array_map(
            static fn(Inventory $i): ?string => $i->getProductId()?->toRfc4122(),
            $this->repository->findLowStock(),
        ));
    }

    public function testFindOutOfStock(): void
    {
        $this->seed($this->productUuid(1), '10.000', '2.000', '10.000');
        $this->seed($this->productUuid(3), '5.000', '5.000', '0.000');

        self::assertSame([$this->productUuid(3)], array_map(
            static fn(Inventory $i): ?string => $i->getProductId()?->toRfc4122(),
            $this->repository->findOutOfStock(),
        ));
    }

    private function seed(string $productId, string $onHand, string $reserved, string $reorder, ?DateTimeImmutable $deletedAt = null): string
    {
        $now = new DateTimeImmutable();
        $inventory = new Inventory()
            ->setProductId(Uuid::fromString($productId))
            ->setQuantityOnHand($onHand)
            ->setQuantityReserved($reserved)
            ->setReorderLevel($reorder)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setDeletedAt($deletedAt);

        $this->em->persist($inventory);
        $this->em->flush();
        $id = $inventory->getId()->toRfc4122();
        $this->em->clear();

        return $id;
    }
}
