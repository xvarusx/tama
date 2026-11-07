<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Brand summary embedded in a product. */
final readonly class BrandRefDocument
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
