<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case CHEQUE = 'CHEQUE';
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case OTHER = 'OTHER';
}