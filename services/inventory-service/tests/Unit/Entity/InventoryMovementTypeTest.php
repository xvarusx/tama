<?php

namespace App\Tests\Unit\Entity;

use App\Enum\InventoryMovementType;
use PHPUnit\Framework\TestCase;

final class InventoryMovementTypeTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(
            ['initial_stock', 'purchase_receipt', 'sale', 'return', 'adjustment', 'receipt', 'write_off', 'reserve', 'release'],
            array_map(static fn (InventoryMovementType $type): string => $type->value, InventoryMovementType::cases()),
        );
    }
}
