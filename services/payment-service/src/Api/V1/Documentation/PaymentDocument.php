<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A single payment as returned by the API. */
final readonly class PaymentDocument
{
    /**
     * @param list<PaymentAllocationDocument> $allocations
     * @param list<ErrorDetailDocument> $errors
     */
    public function __construct(
        public string $id,
        public PaymentAttributesDocument $attributes,
        /** Resource type, always "payment". */
        public string $type = 'payment',
    ) {}
}