<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;

final readonly class InvoiceCreated implements InvoiceDomainEvent
{
    public const string TYPE = 'invoice.created';
    public const int VERSION = 1;

    public function __construct(
        public string $invoiceId,
        public string $orderId,
    ) {}

    public function toOutbox(): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'invoiceId' => $this->invoiceId,
            'orderId' => $this->orderId,
        ]);
    }
}
