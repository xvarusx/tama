<?php

declare(strict_types=1);

namespace App\Tax;

final readonly class TaxLineResult
{
    public function __construct(
        public int $taxRateBps,
        public int $taxAmount,
    ) {}
}
