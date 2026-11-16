<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationStatus: string
{
    case UNREAD = 'unread';
    case READ = 'read';
    case ARCHIVED = 'archived';
}
