<?php

declare(strict_types=1);

namespace App\Tax;

use Symfony\Component\Uid\Uuid;

final readonly class TaxLineInput
{
    public function __construct(
        public Uuid $productId,
        public int $quantity,
        public int $unitPrice,
    ) {}
}
