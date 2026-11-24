<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** One notification in the API v1 envelope. */
final readonly class NotificationDocument
{
    public function __construct(
        public string $id,
        public NotificationAttributesDocument $attributes,
        public string $type = 'notification',
    ) {}
}
