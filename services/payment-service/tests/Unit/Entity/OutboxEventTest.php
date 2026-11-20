<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\OutboxEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OutboxEventTest extends TestCase
{
    public function test_create_outbox_event(): void
    {
        $event = new OutboxEvent(
            'payment.created',
            1,
            ['paymentId' => '11111111-1111-1111-1111-111111111111', 'number' => 'PYMT-001']
        );

        $this->assertSame('payment.created', $event->getEventType());
        $this->assertSame(1, $event->getEventVersion());
        $this->assertSame([
            'paymentId' => '11111111-1111-1111-1111-111111111111',
            'number' => 'PYMT-001'
        ], $event->getPayload());
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getCreatedAt());
        $this->assertNull($event->getPublishedAt());
        $this->assertNull($event->getId()); // Not yet persisted
    }

    public function test_mark_published(): void
    {
        $event = new OutboxEvent(
            'payment.completed',
            1,
            ['paymentId' => '22222222-2222-2222-2222-222222222222']
        );

        $this->assertNull($event->getPublishedAt());

        $event->markPublished();

        $this->assertInstanceOf(DateTimeImmutable::class, $event->getPublishedAt());
    }

    public function test_is_pending_for_publishing(): void
    {
        $pendingEvent = new OutboxEvent(
            'payment.created',
            1,
            ['paymentId' => '11111111-1111-1111-1111-111111111111']
        );

        $publishedEvent = new OutboxEvent(
            'payment.completed',
            1,
            ['paymentId' => '22222222-2222-2222-2222-222222222222']
        );
        $publishedEvent->markPublished();

        // This is testing the domain logic - events with null publishedAt are pending
        $this->assertNull($pendingEvent->getPublishedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $publishedEvent->getPublishedAt());
    }

    public function test_event_type_format(): void
    {
        $event = new OutboxEvent(
            'invoice.issued',
            1,
            ['invoiceId' => '33333333-3333-3333-3333-333333333333']
        );

        $this->assertSame('invoice.issued', $event->getEventType());
    }

    public function test_event_version(): void
    {
        $eventV1 = new OutboxEvent('payment.created', 1, []);
        $eventV2 = new OutboxEvent('payment.created', 2, []);

        $this->assertSame(1, $eventV1->getEventVersion());
        $this->assertSame(2, $eventV2->getEventVersion());
    }

    public function test_created_at_is_set_automatically(): void
    {
        $before = new DateTimeImmutable();
        $event = new OutboxEvent('test.event', 1, []);
        $after = new DateTimeImmutable();

        $createdAt = $event->getCreatedAt();

        // Created at should be between before and after
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $createdAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $createdAt->getTimestamp());
    }
}