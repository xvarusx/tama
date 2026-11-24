<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** One email delivery in the API v1 envelope. */
final readonly class DeliveryDocument
{
    public function __construct(
        public string $id,
        public DeliveryAttributesDocument $attributes,
        public string $type = 'delivery',
    ) {}
}
