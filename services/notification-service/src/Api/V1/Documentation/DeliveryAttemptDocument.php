<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Documented send attempt. */
final readonly class DeliveryAttemptDocument
{
    public function __construct(
        public int $attemptNumber,
        public ?string $providerMessageId,
        public ?string $error,
        public string $attemptedAt,
    ) {}
}
