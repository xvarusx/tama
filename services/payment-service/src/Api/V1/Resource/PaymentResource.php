<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

final readonly class PaymentResource
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $id,
        public array $attributes,
        public string $type = 'payment',
    ) {}
}