<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A single payment plan as returned by the API. */
final readonly class PaymentPlanDocument
{
    /**
     * @param list<InstallmentDocument> $installments
     */
    public function __construct(
        public string $id,
        public PaymentPlanAttributesDocument $attributes,
        /** Resource type, always "payment-plan". */
        public string $type = 'payment-plan',
    ) {}
}