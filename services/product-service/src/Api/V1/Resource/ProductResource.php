<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

final readonly class ProductResource
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public int $id,
        public array $attributes,
        public string $type = 'product',
    ) {}
}
