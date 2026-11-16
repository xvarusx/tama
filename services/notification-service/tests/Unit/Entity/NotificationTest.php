<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
use App\Enum\Severity;
use App\Exceptions\InvalidNotificationTransition;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class NotificationTest extends TestCase
{
    public function testUnreadCanBeReadThenArchived(): void
    {
        $notification = $this->createNotification();

        self::assertSame(NotificationStatus::UNREAD, $notification->getStatus());
        self::assertTrue($notification->isUnread());
        self::assertNull($notification->getReadAt());
        self::assertNull($notification->getArchivedAt());

        $before = new DateTimeImmutable();
        $notification->markAsRead();

        self::assertSame(NotificationStatus::READ, $notification->getStatus());
        self::assertFalse($notification->isUnread());
        self::assertInstanceOf(DateTimeImmutable::class, $notification->getReadAt());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $notification->getReadAt()->getTimestamp());

        $notification->archive();

        self::assertSame(NotificationStatus::ARCHIVED, $notification->getStatus());
        self::assertFalse($notification->isUnread());
        self::assertInstanceOf(DateTimeImmutable::class, $notification->getArchivedAt());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $notification->getArchivedAt()->getTimestamp());
        self::assertNotNull($notification->getReadAt());
    }

    public function testMarkingAnAlreadyReadNotificationIsANoOp(): void
    {
        $notification = $this->createNotification();
        $notification->markAsRead();
        $readAt = $notification->getReadAt();

        $notification->markAsRead();

        self::assertSame(NotificationStatus::READ, $notification->getStatus());
        self::assertSame($readAt, $notification->getReadAt());
    }

    public function testArchivedCannotBeReadAgain(): void
    {
        $notification = $this->createNotification();
        $notification->archive();

        try {
            $notification->markAsRead();
            self::fail('Expected InvalidNotificationTransition to be thrown.');
        } catch (InvalidNotificationTransition $exception) {
            self::assertSame('INVALID_NOTIFICATION_TRANSITION', $exception->getErrorCode());
            self::assertSame(409, $exception->getHttpStatus());
        }

        self::assertSame(NotificationStatus::ARCHIVED, $notification->getStatus());
        self::assertNull($notification->getReadAt());
    }

    public function testArchivingAnArchivedNotificationIsANoOp(): void
    {
        $notification = $this->createNotification();
        $notification->archive();
        $archivedAt = $notification->getArchivedAt();

        $notification->archive();

        self::assertSame(NotificationStatus::ARCHIVED, $notification->getStatus());
        self::assertSame($archivedAt, $notification->getArchivedAt());
    }

    public function testUnreadNotificationCanBeArchivedDirectly(): void
    {
        $notification = $this->createNotification();

        $notification->archive();

        self::assertSame(NotificationStatus::ARCHIVED, $notification->getStatus());
        self::assertNull($notification->getReadAt());
        self::assertNotNull($notification->getArchivedAt());
    }

    public function testConstructorExposesValuesAndGeneratesUuidV7(): void
    {
        $notification = $this->createNotification();

        self::assertInstanceOf(Uuid::class, $notification->getId());
        self::assertSame(36, strlen($notification->getId()->toRfc4122()));
        self::assertInstanceOf(UuidV7::class, $notification->getId());
        self::assertSame(7, $notification->getRecipientId());
        self::assertSame('payment.installment.overdue', $notification->getType());
        self::assertSame('Invoice F-012 overdue', $notification->getTitle());
        self::assertSame('Please settle invoice F-012.', $notification->getBody());
        self::assertSame(Severity::WARNING, $notification->getSeverity());
        self::assertSame('/dashboard/invoices/42', $notification->getLink());
        self::assertSame('installment-3f2a:2026-11-15', $notification->getDedupKey());
        self::assertTrue($notification->isVisibleInApp());
        self::assertTrue($notification->getVisibleInApp());
        self::assertCount(0, $notification->getDeliveries());
    }

    public function testEmailOnlyNotificationIsHiddenFromTheInbox(): void
    {
        $notification = $this->createNotification(visibleInApp: false);

        self::assertFalse($notification->isVisibleInApp());
        self::assertFalse($notification->getVisibleInApp());
        self::assertTrue($notification->isUnread());
    }

    public function testOptionalLinkAndDedupKeyDefaultToNull(): void
    {
        $notification = new Notification(
            12,
            'order.confirmed',
            'Order confirmed',
            'Your order is on its way.',
            Severity::SUCCESS,
            true,
        );

        self::assertNull($notification->getLink());
        self::assertNull($notification->getDedupKey());
    }

    private function createNotification(bool $visibleInApp = true): Notification
    {
        return new Notification(
            7,
            'payment.installment.overdue',
            'Invoice F-012 overdue',
            'Please settle invoice F-012.',
            Severity::WARNING,
            $visibleInApp,
            '/dashboard/invoices/42',
            'installment-3f2a:2026-11-15',
        );
    }
}
