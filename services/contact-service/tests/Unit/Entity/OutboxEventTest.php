<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\OutboxEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class OutboxEventTest extends TestCase
{
    private const CONTACT_ID = '01998f8c-0a6b-7c1d-9a0b-2f6a0ad3d54e';

    public function testOutboxEventCreationWithRequiredFields(): void
    {
        $event = new OutboxEvent('contact.created', 1, ['contactId' => self::CONTACT_ID, 'name' => 'Test Contact']);

        self::assertSame('contact.created', $event->getEventType());
        self::assertSame(1, $event->getEventVersion());
        self::assertSame(['contactId' => self::CONTACT_ID, 'name' => 'Test Contact'], $event->getPayload());
        self::assertInstanceOf(Uuid::class, $event->getId());
        self::assertInstanceOf(DateTimeImmutable::class, $event->getCreatedAt());
        self::assertNull($event->getPublishedAt());
    }

    public function testMarkPublishedSetsPublishedAt(): void
    {
        $event = new OutboxEvent('contact.created', 1, ['contactId' => self::CONTACT_ID]);
        $before = new DateTimeImmutable();

        $event->markPublished();

        $after = new DateTimeImmutable();
        self::assertNotNull($event->getPublishedAt());
        self::assertGreaterThanOrEqual($before, $event->getPublishedAt());
        self::assertLessThanOrEqual($after, $event->getPublishedAt());
    }

    public function testGettersReturnCorrectTypes(): void
    {
        $event = new OutboxEvent('contact.updated', 2, ['contactId' => self::CONTACT_ID]);

        self::assertIsString($event->getEventType());
        self::assertIsInt($event->getEventVersion());
        self::assertIsArray($event->getPayload());
        self::assertInstanceOf(DateTimeImmutable::class, $event->getCreatedAt());
        self::assertNull($event->getPublishedAt());
        self::assertInstanceOf(Uuid::class, $event->getId());
    }
}
