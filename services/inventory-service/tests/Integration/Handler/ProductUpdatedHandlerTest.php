<?php

namespace App\Tests\Integration\Handler;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Entity\Product;
use App\Event\ProductUpdated;
use App\Handler\ProductUpdatedHandler;
use App\Tests\Support\ProductUuidTrait;
use App\Utils\Decimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\ItemInterface;

final class ProductUpdatedHandlerTest extends KernelTestCase
{
    use ProductUuidTrait;

    private EntityManagerInterface $em;
    private ProductUpdatedHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->handler = self::getContainer()->get(ProductUpdatedHandler::class);
        if (!$this->em->getConnection()->createSchemaManager()->tablesExist(['product'])) {
            new SchemaTool($this->em)->createSchema([$this->em->getClassMetadata(Product::class)]);
        }
        $this->em->getConnection()->executeStatement('DELETE FROM inventory_movement');
        $this->em->getConnection()->executeStatement('DELETE FROM inventory');
        $this->em->getConnection()->executeStatement('DELETE FROM product');
        $this->em->clear();
    }

    public function testCreatesInventoryForNewProduct(): void
    {
        ($this->handler)(new ProductUpdated($this->productUuid(501), 'SKU-501', 'Widget', '9.99', 'piece'));

        $this->em->clear();
        $inventory = $this->em->getRepository(Inventory::class)
            ->findOneBy(['productId' => Uuid::fromString($this->productUuid(501))]);
        self::assertNotNull($inventory);
        self::assertSame('0.000', Decimal::format($inventory->getQuantityOnHand()));
        self::assertSame('0.000', Decimal::format($inventory->getQuantityReserved()));
        self::assertSame('0.000', Decimal::format($inventory->getReorderLevel()));
        self::assertSame(1, $inventory->getVersion());
        self::assertNotNull($inventory->getCreatedAt());
        self::assertNotNull($inventory->getUpdatedAt());
    }

    public function testDoesNotResetExistingInventory(): void
    {
        $this->seed($this->productUuid(502), '50.000', '5.000', '10.000');

        ($this->handler)(new ProductUpdated($this->productUuid(502), 'SKU-502', 'Widget', '9.99', 'piece'));

        $this->em->clear();
        $inventory = $this->em->getRepository(Inventory::class)
            ->findOneBy(['productId' => Uuid::fromString($this->productUuid(502))]);
        self::assertNotNull($inventory);
        self::assertNull($inventory->getDeletedAt());
        self::assertSame('50.000', Decimal::format($inventory->getQuantityOnHand()));
        self::assertSame('5.000', Decimal::format($inventory->getQuantityReserved()));
        self::assertSame('10.000', Decimal::format($inventory->getReorderLevel()));
        self::assertCount(0, $this->em->getRepository(InventoryMovement::class)->findAll());
    }

    public function testRestoresSoftDeletedInventoryWithZeroBalancesAndNoMovement(): void
    {
        $this->seed($this->productUuid(505), '0.000', '0.000', '7.000', new DateTimeImmutable());

        $cache = self::getContainer()->get('cache.inventory');
        $calls = 0;
        $probe = function (ItemInterface $item) use (&$calls): string {
            $item->tag(['inventory']);
            ++$calls;

            return 'probe';
        };
        $cache->get('restore_probe', $probe);

        ($this->handler)(new ProductUpdated($this->productUuid(505), 'SKU-505', 'Widget', '9.99', 'piece'));

        $this->em->clear();
        $inventory = $this->em->getRepository(Inventory::class)
            ->findOneBy(['productId' => Uuid::fromString($this->productUuid(505))]);
        self::assertNotNull($inventory);
        self::assertNull($inventory->getDeletedAt());
        self::assertSame('0.000', Decimal::format($inventory->getQuantityOnHand()));
        self::assertSame('0.000', Decimal::format($inventory->getQuantityReserved()));
        self::assertSame('7.000', Decimal::format($inventory->getReorderLevel()));
        self::assertCount(0, $this->em->getRepository(InventoryMovement::class)->findAll());

        $cache->get('restore_probe', $probe);
        self::assertSame(2, $calls);
    }

    public function testDuplicateDeliveryCreatesOneRow(): void
    {
        $productId = $this->productUuid(503);
        ($this->handler)(new ProductUpdated($productId, 'SKU-503', 'Widget', '9.99', 'piece'));
        ($this->handler)(new ProductUpdated($productId, 'SKU-503', 'Widget', '9.99', 'piece'));

        self::assertCount(1, $this->em->getRepository(Inventory::class)
            ->findBy(['productId' => Uuid::fromString($productId)]));
        self::assertCount(0, $this->em->getRepository(InventoryMovement::class)->findAll());
    }

    public function testInvalidatesInventoryCache(): void
    {
        $cache = self::getContainer()->get('cache.inventory');
        $calls = 0;
        $probe = function (ItemInterface $item) use (&$calls): string {
            $item->tag(['inventory']);
            ++$calls;

            return 'probe';
        };

        $cache->get('handler_probe', $probe);
        ($this->handler)(new ProductUpdated($this->productUuid(504), 'SKU-504', 'Widget', '9.99', 'piece'));
        $cache->get('handler_probe', $probe);

        self::assertSame(2, $calls);
    }

    private function seed(string $productId, string $onHand, string $reserved, string $reorder, ?DateTimeImmutable $deletedAt = null): void
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
        $this->em->clear();
    }
}
