<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The attributes block of a reminder resource. */
final readonly class ReminderAttributesDocument
{
    public function __construct(
        public string $installmentId,
        public string $kind,
        public string $scheduledFor,
        public string $status,
        public ?string $sentAt,
    ) {}
}