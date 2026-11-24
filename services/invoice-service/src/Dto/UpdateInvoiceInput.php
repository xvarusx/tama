<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class UpdateInvoiceInput
{
    /** @param list<InvoiceLineInput> $lines */
    public function __construct(
        public ?string $partyName,
        public ?string $currency,
        public array $lines,
    ) {}
}
