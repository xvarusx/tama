<?php

namespace App\Tests\Unit\Entity;

use App\Enum\InventoryMovementType;
use PHPUnit\Framework\TestCase;

final class InventoryMovementTypeTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(
            ['in', 'out', 'adjustment'],
            array_map(static fn(InventoryMovementType $type): string => $type->value, InventoryMovementType::cases()),
        );
    }
}
