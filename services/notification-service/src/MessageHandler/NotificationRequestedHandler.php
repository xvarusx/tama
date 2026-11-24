<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exceptions\InvalidNotificationEvent;
use App\Handler\Notification\CreateNotificationsHandler;
use App\Message\NotificationRequested;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class NotificationRequestedHandler
{
    public function __construct(private CreateNotificationsHandler $createNotifications) {}

    public function __invoke(NotificationRequested $message): void
    {
        try {
            ($this->createNotifications)($message);
        } catch (InvalidNotificationEvent $exception) {
            // Retrying a malformed payload cannot help: dead-letter it instead.
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        }
    }
}
