<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;

final readonly class InvoicePaid implements InvoiceDomainEvent
{
    public const string TYPE = 'invoice.paid';
    public const int VERSION = 1;

    public function __construct(public string $invoiceId) {}

    public function toOutbox(): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, ['invoiceId' => $this->invoiceId]);
    }
}
