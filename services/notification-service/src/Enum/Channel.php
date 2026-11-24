<?php

declare(strict_types=1);

namespace App\Enum;

enum Channel: string
{
    case IN_APP = 'in_app';
    case EMAIL = 'email';
}
