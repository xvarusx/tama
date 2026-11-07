<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Machine-readable error code and human-readable message. */
final readonly class ErrorDetailDocument
{
    public function __construct(
        /** Stable error code, e.g. "SKU_ALREADY_EXISTS". */
        public string $code,
        public string $message,
    ) {}
}
