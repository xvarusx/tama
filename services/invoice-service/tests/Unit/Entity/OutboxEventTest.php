<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\OutboxEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OutboxEventTest extends TestCase
{
    public function testMarkPublishedSetsTimestamp(): void
    {
        $event = new OutboxEvent('invoice.created', 1, ['invoiceId' => 'x']);
        self::assertNull($event->getPublishedAt());
        $event->markPublished();
        self::assertNotNull($event->getPublishedAt());
    }

    public function testPayloadAndTypeAreExposed(): void
    {
        $event = new OutboxEvent('invoice.issued', 1, ['invoiceId' => 'x', 'number' => 'INV-2026-000001']);

        self::assertSame('invoice.issued', $event->getEventType());
        self::assertSame(1, $event->getEventVersion());
        self::assertSame('INV-2026-000001', $event->getPayload()['number']);
        self::assertSame(new DateTimeImmutable()->format('Y-m-d'), $event->getCreatedAt()->format('Y-m-d'));
    }
}
