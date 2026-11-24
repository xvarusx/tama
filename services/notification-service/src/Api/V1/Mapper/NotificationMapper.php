<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\NotificationResource;
use App\Entity\Notification;
use DateTimeImmutable;

/** Entity -> API v1 contract for the inbox. */
final class NotificationMapper
{
    public function toResource(Notification $notification): NotificationResource
    {
        return new NotificationResource(
            id: (string)$notification->getId(),
            attributes: [
                'recipientId' => $notification->getRecipientId()->toRfc4122(),
                'type' => $notification->getType(),
                'title' => $notification->getTitle(),
                'body' => $notification->getBody(),
                'link' => $notification->getLink(),
                'severity' => $notification->getSeverity()->value,
                'status' => $notification->getStatus()->value,
                'visibleInApp' => $notification->isVisibleInApp(),
                'isUnread' => $notification->isUnread(),
                'dedupKey' => $notification->getDedupKey(),
                'readAt' => self::formatDate($notification->getReadAt()),
                'archivedAt' => self::formatDate($notification->getArchivedAt()),
                'createdAt' => self::formatDate($notification->getCreatedAt()),
                'updatedAt' => self::formatDate($notification->getUpdatedAt()),
            ],
        );
    }

    private static function formatDate(?DateTimeImmutable $date): ?string
    {
        return $date?->format(\DATE_ATOM);
    }
}
