<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Category summary embedded in a product. Null when the product has no category. */
final readonly class CategoryRefDocument
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
