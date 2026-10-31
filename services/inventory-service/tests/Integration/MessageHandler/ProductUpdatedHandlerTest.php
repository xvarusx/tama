<?php

namespace App\Tests\Integration\MessageHandler;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Message\ProductUpdated;
use App\MessageHandler\ProductUpdatedHandler;
use App\Util\Decimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\ItemInterface;

final class ProductUpdatedHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProductUpdatedHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->handler = self::getContainer()->get(ProductUpdatedHandler::class);
        $this->em->getConnection()->executeStatement('DELETE FROM inventory_movement');
        $this->em->getConnection()->executeStatement('DELETE FROM inventory');
        $this->em->getConnection()->executeStatement('DELETE FROM product');
        $this->em->clear();
    }

    public function testCreatesInventoryForNewProduct(): void
    {
        ($this->handler)(new ProductUpdated(501, 'SKU-501', 'Widget', '9.99', 'piece'));

        $this->em->clear();
        $inventory = $this->em->getRepository(Inventory::class)->findOneBy(['productId' => 501]);
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
        $this->seed(502, '50.000', '5.000', '10.000');

        ($this->handler)(new ProductUpdated(502, 'SKU-502', 'Widget', '9.99', 'piece'));

        $this->em->clear();
        $inventory = $this->em->getRepository(Inventory::class)->findOneBy(['productId' => 502]);
        self::assertNotNull($inventory);
        self::assertNull($inventory->getDeletedAt());
        self::assertSame('50.000', Decimal::format($inventory->getQuantityOnHand()));
        self::assertSame('5.000', Decimal::format($inventory->getQuantityReserved()));
        self::assertSame('10.000', Decimal::format($inventory->getReorderLevel()));
        self::assertCount(0, $this->em->getRepository(InventoryMovement::class)->findAll());
    }

    public function testRestoresSoftDeletedInventoryWithZeroBalancesAndNoMovement(): void
    {
        $this->seed(505, '0.000', '0.000', '7.000', new \DateTimeImmutable());

        $cache = self::getContainer()->get('cache.inventory');
        $calls = 0;
        $probe = function (ItemInterface $item) use (&$calls): string {
            $item->tag(['inventory']);
            ++$calls;

            return 'probe';
        };
        $cache->get('restore_probe', $probe);

        ($this->handler)(new ProductUpdated(505, 'SKU-505', 'Widget', '9.99', 'piece'));

        $this->em->clear();
        $inventory = $this->em->getRepository(Inventory::class)->findOneBy(['productId' => 505]);
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
        ($this->handler)(new ProductUpdated(503, 'SKU-503', 'Widget', '9.99', 'piece'));
        ($this->handler)(new ProductUpdated(503, 'SKU-503', 'Widget', '9.99', 'piece'));

        self::assertCount(1, $this->em->getRepository(Inventory::class)->findBy(['productId' => 503]));
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
        ($this->handler)(new ProductUpdated(504, 'SKU-504', 'Widget', '9.99', 'piece'));
        $cache->get('handler_probe', $probe);

        self::assertSame(2, $calls);
    }

    private function seed(int $productId, string $onHand, string $reserved, string $reorder, ?\DateTimeImmutable $deletedAt = null): void
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
        $this->em->clear();
    }
}
