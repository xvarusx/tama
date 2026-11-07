<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A unit as returned by the API. */
final readonly class UnitDocument
{
    public function __construct(
        public int $id,
        public string $code,
        public bool $allowDecimal,
    ) {}
}
