<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The SKU that would be generated for the given product name and options. */
final readonly class SkuPreviewDocument
{
    public function __construct(
        public string $sku,
    ) {}
}
