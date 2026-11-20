<?php

declare(strict_types=1);

namespace App\Enum;

enum InstallmentStatus: string
{
    case UNPAID = 'UNPAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';
    case CANCELED = 'CANCELED';
}