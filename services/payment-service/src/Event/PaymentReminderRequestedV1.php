<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use DateTimeImmutable;

/**
 * Contract v1 of payment.reminder.requested - an outbound event for reminder notifications.
 */
final class PaymentReminderRequestedV1
{
    public const string TYPE = 'payment.reminder.requested';
    public const int VERSION = 1;

    private function __construct(
        public readonly string $installmentId,
        public readonly string $invoiceId,
        public readonly \DateTimeInterface $dueDate,
        public readonly \DateTimeInterface $occurredAt,
    ) {}

    public static function create(string $installmentId, string $invoiceId, \DateTimeInterface $dueDate): self
    {
        return new self(
            installmentId: $installmentId,
            invoiceId: $invoiceId,
            dueDate: $dueDate,
            occurredAt: new DateTimeImmutable(),
        );
    }

    public static function toOutbox(self $event): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'installmentId' => $event->installmentId,
            'invoiceId' => $event->invoiceId,
            'dueDate' => $event->dueDate->format(\DATE_ATOM),
            'occurredAt' => $event->occurredAt->format(\DATE_ATOM),
        ]);
    }
}