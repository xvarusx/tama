<?php

declare(strict_types=1);

namespace App\Message;

/** A payment was received for an invoice. `paymentId` is the idempotency key. */
final readonly class PaymentReceivedMessage
{
    public function __construct(
        public string $invoiceId,
        public string $paymentId,
        public int $amount,
        public string $currency,
        public string $occurredAt,
    ) {}
}
