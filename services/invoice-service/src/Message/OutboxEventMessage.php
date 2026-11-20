<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\OutboxEvent;

/** Carries one outbox row onto the broker. See the outbox publisher command. */
final readonly class OutboxEventMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $eventType,
        public int $eventVersion,
        public array $payload,
    ) {}

    public static function fromOutbox(OutboxEvent $event): self
    {
        return new self($event->getEventType(), $event->getEventVersion(), $event->getPayload());
    }
}
