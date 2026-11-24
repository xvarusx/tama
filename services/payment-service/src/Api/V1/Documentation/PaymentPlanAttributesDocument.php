<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The attributes block of a payment plan resource. */
final readonly class PaymentPlanAttributesDocument
{
    /**
     * @param list<InstallmentDocument> $installments
     */
    public function __construct(
        public string $invoiceId,
        public int $totalAmount,
        public string $currency,
        public string $status,
        public int $version,
        public array $installments,
    ) {}
}