<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A single order as returned by the API. */
final readonly class OrderDocument
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $id,
        public array $attributes,
        public string $type = 'order',
    ) {}
}