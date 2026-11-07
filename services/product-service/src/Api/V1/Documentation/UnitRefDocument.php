<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Unit summary embedded in a product. */
final readonly class UnitRefDocument
{
    public function __construct(
        public int $id,
        public string $code,
    ) {}
}
