<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Order types: Purchase orders are inputs (bought from suppliers),
 * Sales orders are outputs (sold to customers).
 */
enum OrderType: string
{
    case PURCHASE = 'purchase';
    case SALES = 'sales';
}