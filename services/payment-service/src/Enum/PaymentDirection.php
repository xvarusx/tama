<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentDirection: string
{
    case INCOMING = 'INCOMING';
    case OUTGOING = 'OUTGOING';
}