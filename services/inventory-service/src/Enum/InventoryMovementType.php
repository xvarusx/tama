<?php

declare(strict_types=1);

namespace App\Enum;

enum InventoryMovementType: string
{
    case IN = 'in';
    case OUT = 'out';
    case ADJUSTMENT = 'adjustment';
}
