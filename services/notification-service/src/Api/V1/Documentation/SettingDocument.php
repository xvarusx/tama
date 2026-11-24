<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Documented setting. */
final readonly class SettingDocument
{
    public function __construct(
        public string $key,
        public string $value,
        public string $description,
    ) {}
}
