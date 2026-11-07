<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A single product as returned by the API. */
final readonly class ProductDocument
{
    public function __construct(
        public int $id,
        public ProductAttributesDocument $attributes,
        /** Resource type, always "product". */
        public string $type = 'product',
    ) {}
}
