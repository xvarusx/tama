<?php

declare(strict_types=1);

namespace App\Enum;

enum ContactType: string
{
    case CLIENT = 'client';
    case SUPPLIER = 'supplier';
}
