<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A brand as returned by the API. */
final readonly class BrandDocument
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}
}
