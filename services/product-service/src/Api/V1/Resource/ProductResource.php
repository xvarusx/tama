<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

final class ProductResource
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public readonly int $id,
        public readonly array $attributes,
        public readonly string $type = 'product',
    ) {}
}
