<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enum\NotificationStatus;

final class InvalidNotificationTransition extends ApiException
{
    public function __construct(NotificationStatus $status, string $action)
    {
        parent::__construct(sprintf('Cannot perform "%s" on a notification in status "%s".', $action, $status->value));
    }

    public function getErrorCode(): string
    {
        return 'INVALID_NOTIFICATION_TRANSITION';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
