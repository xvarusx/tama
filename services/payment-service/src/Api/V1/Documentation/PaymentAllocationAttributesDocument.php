<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The attributes block of a payment allocation resource. */
final readonly class PaymentAllocationAttributesDocument
{
    public function __construct(
        public string $paymentId,
        public string $installmentId,
        public string $invoiceId,
        public int $amount,
    ) {}
}