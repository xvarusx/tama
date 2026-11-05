<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

final class VariantResource
{
    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly ?string $barcode,
        public readonly ?array $options,
        public readonly string $sellingPrice,
        public readonly ?string $costPrice = null,
    ) {}
}
