<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use App\Entity\Notification;
use App\Enum\Channel;
use App\Enum\DeliveryStatus;
use App\Enum\Severity;
use App\Exceptions\InvalidDeliveryTransition;
use App\Utils\DemoUserUuids;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class DeliveryTest extends TestCase
{
    public function testQueuedDeliveryBecomesSendingThenSent(): void
    {
        $delivery = $this->createDelivery();

        self::assertSame(DeliveryStatus::QUEUED, $delivery->getStatus());
        self::assertSame(0, $delivery->getAttempts());
        self::assertNull($delivery->getSendingAt());
        self::assertNull($delivery->getSentAt());
        self::assertNotNull($delivery->getNextAttemptAt());

        $before = new DateTimeImmutable();
        $delivery->markSending();

        self::assertSame(DeliveryStatus::SENDING, $delivery->getStatus());
        self::assertSame(1, $delivery->getAttempts());
        self::assertInstanceOf(DateTimeImmutable::class, $delivery->getSendingAt());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $delivery->getSendingAt()->getTimestamp());

        $delivery->markSent();

        self::assertSame(DeliveryStatus::SENT, $delivery->getStatus());
        self::assertSame(1, $delivery->getAttempts());
        self::assertInstanceOf(DateTimeImmutable::class, $delivery->getSentAt());
        self::assertNull($delivery->getLastError());
    }

    public function testTemporaryFailureSchedulesRetryWithError(): void
    {
        $delivery = $this->createDelivery();
        $delivery->markSending();

        $before = new DateTimeImmutable();
        $delivery->scheduleRetry(30, 'Connection to the SMTP server timed out.');

        self::assertSame(DeliveryStatus::RETRY_WAITING, $delivery->getStatus());
        self::assertSame('Connection to the SMTP server timed out.', $delivery->getLastError());
        self::assertSame(1, $delivery->getAttempts());
        self::assertInstanceOf(DateTimeImmutable::class, $delivery->getNextAttemptAt());
        self::assertGreaterThanOrEqual(
            $before->modify('+30 seconds')->getTimestamp(),
            $delivery->getNextAttemptAt()->getTimestamp(),
        );

        $delivery->markSending();

        self::assertSame(DeliveryStatus::SENDING, $delivery->getStatus());
        self::assertSame(2, $delivery->getAttempts());
    }

    public function testRetryAfterMaxIsFailedAndRequeueResets(): void
    {
        $delivery = $this->createDelivery();
        $delivery->markSending();
        $delivery->scheduleRetry(10, 'Temporary failure.');
        $delivery->markSending();

        $delivery->markFailed('550 5.1.1 Mailbox unavailable.');

        self::assertSame(DeliveryStatus::FAILED, $delivery->getStatus());
        self::assertSame('550 5.1.1 Mailbox unavailable.', $delivery->getLastError());
        self::assertSame(2, $delivery->getAttempts());

        $delivery->requeue();

        self::assertSame(DeliveryStatus::QUEUED, $delivery->getStatus());
        self::assertSame(0, $delivery->getAttempts());
        self::assertNull($delivery->getLastError());
        self::assertNull($delivery->getSendingAt());
        self::assertNotNull($delivery->getNextAttemptAt());
        self::assertLessThanOrEqual(
            new DateTimeImmutable()->getTimestamp(),
            $delivery->getNextAttemptAt()->getTimestamp(),
        );
    }

    public function testRequeueOnlyAcceptsFailed(): void
    {
        $delivery = $this->createDelivery();

        try {
            $delivery->requeue();
            self::fail('Expected InvalidDeliveryTransition to be thrown.');
        } catch (InvalidDeliveryTransition $exception) {
            self::assertSame('INVALID_DELIVERY_TRANSITION', $exception->getErrorCode());
            self::assertSame(409, $exception->getHttpStatus());
        }

        self::assertSame(DeliveryStatus::QUEUED, $delivery->getStatus());

        $delivery->markSending();
        $delivery->markSent();

        try {
            $delivery->requeue();
            self::fail('Expected InvalidDeliveryTransition to be thrown for a sent delivery.');
        } catch (InvalidDeliveryTransition) {
        }

        self::assertSame(DeliveryStatus::SENT, $delivery->getStatus());
    }

    public function testStaleSendingIsReclaimedToRetryWaiting(): void
    {
        $delivery = $this->createDelivery();
        $delivery->markSending();
        self::assertSame(1, $delivery->getAttempts());

        $before = new DateTimeImmutable();
        $delivery->reclaimStale();

        self::assertSame(DeliveryStatus::RETRY_WAITING, $delivery->getStatus());
        self::assertSame('Reclaimed after timeout', $delivery->getLastError());
        self::assertSame(2, $delivery->getAttempts());
        self::assertInstanceOf(DateTimeImmutable::class, $delivery->getNextAttemptAt());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $delivery->getNextAttemptAt()->getTimestamp());
    }

    public function testReclaimRequiresSending(): void
    {
        $delivery = $this->createDelivery();

        $this->expectException(InvalidDeliveryTransition::class);

        $delivery->reclaimStale();
    }

    public function testScheduleRetryRequiresSending(): void
    {
        $delivery = $this->createDelivery();

        $this->expectException(InvalidDeliveryTransition::class);

        $delivery->scheduleRetry(30, 'Temporary failure.');
    }

    public function testMarkSentRequiresSending(): void
    {
        $delivery = $this->createDelivery();

        $this->expectException(InvalidDeliveryTransition::class);

        $delivery->markSent();
    }

    public function testDeliverySnapshotsContentAndBelongsToNotification(): void
    {
        $notification = $this->createNotification();
        $delivery = new Delivery(
            $notification,
            Channel::EMAIL,
            'jane@example.com',
            'Invoice F-012 overdue',
            '<p>Please settle invoice F-012.</p>',
        );

        self::assertInstanceOf(Uuid::class, $delivery->getId());
        self::assertInstanceOf(UuidV7::class, $delivery->getId());
        self::assertSame($notification, $delivery->getNotification());
        self::assertSame(Channel::EMAIL, $delivery->getChannel());
        self::assertSame('jane@example.com', $delivery->getRecipientEmail());
        self::assertSame('Invoice F-012 overdue', $delivery->getSubject());
        self::assertSame('<p>Please settle invoice F-012.</p>', $delivery->getBody());
        self::assertCount(1, $notification->getDeliveries());
        self::assertCount(0, $delivery->getDeliveryAttempts());
    }

    public function testAttemptsAreCascadedFromTheDelivery(): void
    {
        $delivery = $this->createDelivery();
        $delivery->markSending();
        $delivery->markSent();

        $attempt = new DeliveryAttempt($delivery, 1, 'provider-message-id', null);

        self::assertInstanceOf(Uuid::class, $attempt->getId());
        self::assertInstanceOf(UuidV7::class, $attempt->getId());
        self::assertSame($delivery, $attempt->getDelivery());
        self::assertSame(1, $attempt->getAttemptNumber());
        self::assertSame('provider-message-id', $attempt->getProviderMessageId());
        self::assertNull($attempt->getError());
        self::assertInstanceOf(DateTimeImmutable::class, $attempt->getAttemptedAt());
        self::assertCount(1, $delivery->getDeliveryAttempts());
    }

    private function createDelivery(): Delivery
    {
        return new Delivery(
            $this->createNotification(),
            Channel::EMAIL,
            'jane@example.com',
            'Invoice F-012 overdue',
            '<p>Please settle invoice F-012.</p>',
        );
    }

    private function createNotification(): Notification
    {
        return new Notification(
            Uuid::fromString(DemoUserUuids::forNumber(7)),
            'payment.installment.overdue',
            'Invoice F-012 overdue',
            'Please settle invoice F-012.',
            Severity::WARNING,
            true,
        );
    }
}
