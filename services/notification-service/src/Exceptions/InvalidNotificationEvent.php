<?php

declare(strict_types=1);

namespace App\Exceptions;

/** A malformed NotificationRequested payload. The consumer dead-letters it. */
final class InvalidNotificationEvent extends ApiException
{
    public function getErrorCode(): string
    {
        return 'INVALID_NOTIFICATION_EVENT';
    }

    public function getHttpStatus(): int
    {
        return 422;
    }
}
