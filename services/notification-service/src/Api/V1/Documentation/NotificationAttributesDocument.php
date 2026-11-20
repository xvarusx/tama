<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Documented attributes of an inbox notification. */
final readonly class NotificationAttributesDocument
{
    public function __construct(
        public string $recipientId,
        public string $type,
        public string $title,
        public string $body,
        public ?string $link,
        public string $severity,
        public string $status,
        public bool $visibleInApp,
        public bool $isUnread,
        public ?string $dedupKey,
        public ?string $readAt,
        public ?string $archivedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
