<?php

declare(strict_types=1);

namespace App\Enum;

enum ContactStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';       // archived, no new orders, can be reactivated
    case BLACKLISTED = 'blacklisted'; // blocked from any new order
}
