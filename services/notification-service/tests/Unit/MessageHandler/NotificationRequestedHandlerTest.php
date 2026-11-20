<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Exceptions\InvalidNotificationEvent;
use App\Handler\Notification\CreateNotificationsHandler;
use App\Message\NotificationRequested;
use App\MessageHandler\NotificationRequestedHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class NotificationRequestedHandlerTest extends TestCase
{
    public function testDelegatesToTheCreateHandler(): void
    {
        $event = NotificationRequested::fromArray([
            'eventId' => '6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10',
            'occurredAt' => '2026-11-15T09:00:00Z',
            'source' => 'payment-service',
            'type' => 'payment.installment.overdue',
            'audience' => ['role' => 'ACCOUNTANT'],
            'params' => [],
        ]);

        $create = $this->createMock(CreateNotificationsHandler::class);
        $create->expects(self::once())->method('__invoke')->with($event)->willReturn(1);

        (new NotificationRequestedHandler($create))($event);
    }

    public function testInvalidPayloadIsSentToTheDeadLetterTransport(): void
    {
        $event = NotificationRequested::fromArray([
            'eventId' => '6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10',
            'occurredAt' => '2026-11-15T09:00:00Z',
            'source' => 'payment-service',
            'type' => 'payment.installment.overdue',
            'audience' => ['role' => 'ACCOUNTANT'],
            'params' => [],
        ]);

        $create = $this->createMock(CreateNotificationsHandler::class);
        $create->expects(self::once())
            ->method('__invoke')
            ->willThrowException(new InvalidNotificationEvent('Invalid audience.'));

        $this->expectException(UnrecoverableMessageHandlingException::class);

        (new NotificationRequestedHandler($create))($event);
    }
}
