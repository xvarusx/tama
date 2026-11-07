<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A product variant (SKU) as returned by the API. */
final readonly class VariantDocument
{
    /** @param array<string, mixed>|null $options e.g. {"size": "M", "color": "red"} */
    public function __construct(
        public int $id,
        public string $sku,
        public ?string $barcode,
        public ?array $options,
        /** Decimal price as a string, e.g. "29.99". */
        public string $sellingPrice,
        /** Decimal price as a string, e.g. "19.99". */
        public ?string $costPrice,
    ) {}
}
