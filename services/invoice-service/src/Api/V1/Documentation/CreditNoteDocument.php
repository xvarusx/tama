<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A credit note issued against an invoice. */
final readonly class CreditNoteDocument
{
    public function __construct(
        public string $number,
        public int $amount,
        public string $reason,
        public string $issuedAt,
    ) {}
}
