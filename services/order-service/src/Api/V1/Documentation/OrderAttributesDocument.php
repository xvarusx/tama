<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Attributes for an order document. */
final readonly class OrderAttributesDocument
{
    public function __construct(
        public string $number,
        public string $type,
        public string $status,
        public string $partyId,
        public string $partyName,
        public string $currency,
        public int $totalAmount,
        public int $totalProcessed,
        public ?string $invoiceId = null,
        public ?string $notes = null,
        public array $lines = [],
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $submittedAt = null,
        public int $version = 1,
    ) {}
}