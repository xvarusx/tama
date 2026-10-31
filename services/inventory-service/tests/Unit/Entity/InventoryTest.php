<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Enum\InventoryMovementType;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function testAvailableQuantityIsTheDecimalDifference(): void
    {
        $inventory = (new Inventory())
            ->setQuantityOnHand('100.000')
            ->setQuantityReserved('20.500');

        self::assertSame('79.500', $inventory->getAvailableQuantity());
    }

    public function testAvailableQuantityCanBeNegative(): void
    {
        $inventory = (new Inventory())
            ->setQuantityOnHand('5.000')
            ->setQuantityReserved('10.000');

        self::assertSame('-5.000', $inventory->getAvailableQuantity());
    }

    public function testLowStockUsesLessThanOrEqual(): void
    {
        $inventory = (new Inventory())
            ->setQuantityOnHand('100.000')
            ->setQuantityReserved('90.000')
            ->setReorderLevel('10.000');

        self::assertTrue($inventory->isLowStock());
    }

    public function testNotLowStockAboveReorderLevel(): void
    {
        $inventory = (new Inventory())
            ->setQuantityOnHand('100.000')
            ->setQuantityReserved('10.000')
            ->setReorderLevel('20.000');

        self::assertFalse($inventory->isLowStock());
    }

    public function testMovementCollectionKeepsBothSidesInSync(): void
    {
        $inventory = (new Inventory())->setProductId(42);
        $movement = (new InventoryMovement())
            ->setType(InventoryMovementType::Sale)
            ->setQuantity('5.000')
            ->setCreatedAt(new \DateTimeImmutable());

        $inventory->addMovement($movement);

        self::assertTrue($inventory->getMovements()->contains($movement));
        self::assertSame($inventory, $movement->getInventory());
        self::assertSame(42, $movement->getProductId());

        $inventory->removeMovement($movement);

        self::assertFalse($inventory->getMovements()->contains($movement));
        self::assertNull($movement->getInventory());
    }

    public function testVersionStartsAtOne(): void
    {
        self::assertSame(1, (new Inventory())->getVersion());
    }
}
