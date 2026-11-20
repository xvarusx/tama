<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;

final readonly class InvoiceIssued implements InvoiceDomainEvent
{
    public const string TYPE = 'invoice.issued';
    public const int VERSION = 1;

    public function __construct(
        public string $invoiceId,
        public string $number,
    ) {}

    public function toOutbox(): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'invoiceId' => $this->invoiceId,
            'number' => $this->number,
        ]);
    }
}
