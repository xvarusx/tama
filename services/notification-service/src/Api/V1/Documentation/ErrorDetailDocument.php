<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Error detail produced for expected business errors. */
final readonly class ErrorDetailDocument
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}
}
