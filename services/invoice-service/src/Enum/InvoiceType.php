<?php

declare(strict_types=1);

namespace App\Enum;

enum InvoiceType: string
{
    case SALES = 'sales';
    case PURCHASE = 'purchase';

    public function numberPrefix(): string
    {
        return $this === self::SALES ? 'INV' : 'BILL';
    }
}
