<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

final readonly class VariantResource
{
    public function __construct(
        public int $id,
        public string $sku,
        public ?string $barcode,
        public ?array $options,
        public string $sellingPrice,
        public ?string $costPrice = null,
    ) {}
}
