<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

/** API v1 representation of a notification template. */
final readonly class TemplateResource
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public string $id,
        public array $attributes,
        public string $type = 'template',
    ) {}
}
