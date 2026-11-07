<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A category as returned by the API. */
final readonly class CategoryDocument
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $position,
    ) {}
}
