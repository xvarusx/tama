<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

final readonly class SettingResource
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $key,
        public array $attributes,
    ) {}
}