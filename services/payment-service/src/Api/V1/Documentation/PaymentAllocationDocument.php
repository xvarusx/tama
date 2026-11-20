<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A single payment allocation as returned by the API. */
final readonly class PaymentAllocationDocument
{
    public function __construct(
        public string $id,
        public PaymentAllocationAttributesDocument $attributes,
        /** Resource type, always "payment-allocation". */
        public string $type = 'payment-allocation',
    ) {}
}