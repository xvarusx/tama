<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Error envelope produced for expected business errors. */
final readonly class ErrorDocument
{
    public function __construct(
        /** Always "error". */
        public string $status,
        public ErrorDetailDocument $error,
    ) {}
}
