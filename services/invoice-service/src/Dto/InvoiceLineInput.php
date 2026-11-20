<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Uid\Uuid;

final readonly class InvoiceLineInput
{
    public function __construct(
        public Uuid $productId,
        public string $description,
        public int $quantity,
        public int $unitPrice,
    ) {}
}
