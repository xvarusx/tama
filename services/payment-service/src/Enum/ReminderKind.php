<?php

declare(strict_types=1);

namespace App\Enum;

enum ReminderKind: string
{
    case UPCOMING_DUE = 'UPCOMING_DUE';
    case OVERDUE = 'OVERDUE';
}