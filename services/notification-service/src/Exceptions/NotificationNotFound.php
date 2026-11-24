<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\Uid\Uuid;

final class NotificationNotFound extends ApiException
{
    public function __construct(Uuid|string $recipientId, Uuid $notificationId)
    {
        parent::__construct(sprintf('Notification "%s" not found for recipient %s.', (string)$notificationId, (string)$recipientId));
    }

    public function getErrorCode(): string
    {
        return 'NOTIFICATION_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }
}
