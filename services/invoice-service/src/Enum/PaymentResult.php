<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentResult: string
{
    case APPLIED = 'applied';
    case FLAGGED = 'flagged';
    case DUPLICATE = 'duplicate';
}
