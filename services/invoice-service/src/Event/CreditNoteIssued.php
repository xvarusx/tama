<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;

final readonly class CreditNoteIssued implements InvoiceDomainEvent
{
    public const string TYPE = 'invoice.credit_note_issued';
    public const int VERSION = 1;

    public function __construct(
        public string $invoiceId,
        public int $amount,
    ) {}

    public function toOutbox(): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'invoiceId' => $this->invoiceId,
            'amount' => $this->amount,
        ]);
    }
}
