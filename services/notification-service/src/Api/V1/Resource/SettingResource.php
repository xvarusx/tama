<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

/** API v1 representation of a setting. */
final readonly class SettingResource
{
    public function __construct(
        public string $key,
        public string $value,
        public string $description,
    ) {}
}
