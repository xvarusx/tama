<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Documented delivery attributes. */
final readonly class DeliveryAttributesDocument
{
    public function __construct(
        public array $notification,
        public string $recipientEmail,
        public string $channel,
        public string $status,
        public int $attempts,
        public string $subject,
        public ?string $body,
        public ?string $nextAttemptAt,
        public ?string $sendingAt,
        public ?string $lastError,
        public ?string $sentAt,
        public ?string $createdAt,
        public ?string $updatedAt,
        /** @var list<DeliveryAttemptDocument> $attemptHistory */
        public array $attemptHistory,
    ) {}
}
