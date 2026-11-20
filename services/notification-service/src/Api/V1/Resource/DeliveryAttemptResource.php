<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

/** One send attempt recorded by the delivery worker. */
final readonly class DeliveryAttemptResource
{
    public function __construct(
        public int $attemptNumber,
        public ?string $providerMessageId,
        public ?string $error,
        public string $attemptedAt,
    ) {}
}
