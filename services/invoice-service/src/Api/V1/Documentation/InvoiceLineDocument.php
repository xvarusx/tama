<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** One invoice line; money is in minor units, tax rate in basis points. */
final readonly class InvoiceLineDocument
{
    public function __construct(
        public string $productId,
        public string $description,
        public int $quantity,
        public int $unitPrice,
        public int $taxRateBps,
        public int $taxAmount,
        public int $subtotal,
        public int $total,
        public int $position,
    ) {}
}
