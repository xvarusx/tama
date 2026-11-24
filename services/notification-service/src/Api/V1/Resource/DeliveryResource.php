<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

/** API v1 representation of an email delivery (admin only). */
final readonly class DeliveryResource
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public string $id,
        public array $attributes,
        public string $type = 'delivery',
    ) {}
}
