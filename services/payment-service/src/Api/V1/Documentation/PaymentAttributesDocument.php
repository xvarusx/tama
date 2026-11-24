<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The attributes block of a payment resource. */
final readonly class PaymentAttributesDocument
{
    /**
     * @param list<PaymentAllocationDocument> $allocations
     * @param list<ErrorDetailDocument> $errors
     */
    public function __construct(
        public string $number,
        public string $direction,
        public string $method,
        public string $status,
        public string $partyId,
        public string $partyName,
        public string $currency,
        public int $amount,
        public ?string $paidAt,
        public ?string $reference,
        public int $version,
        public array $allocations,
    ) {}
}