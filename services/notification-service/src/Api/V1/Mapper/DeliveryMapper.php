<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\DeliveryAttemptResource;
use App\Api\V1\Resource\DeliveryResource;
use App\Entity\Delivery;
use DateTimeImmutable;

/** Entity -> API v1 contract for the admin delivery views. */
final class DeliveryMapper
{
    public function toResource(Delivery $delivery, bool $withBody = false): DeliveryResource
    {
        $notification = $delivery->getNotification();
        $attempts = array_values(array_map(
            static fn($attempt): DeliveryAttemptResource => new DeliveryAttemptResource(
                attemptNumber: $attempt->getAttemptNumber(),
                providerMessageId: $attempt->getProviderMessageId(),
                error: $attempt->getError(),
                attemptedAt: self::formatDate($attempt->getAttemptedAt()) ?? '',
            ),
            $delivery->getDeliveryAttempts()->toArray(),
        ));

        $attributes = [
            'notification' => [
                'id' => (string)$notification->getId(),
                'type' => $notification->getType(),
                'title' => $notification->getTitle(),
            ],
            'recipientEmail' => $delivery->getRecipientEmail(),
            'channel' => $delivery->getChannel()->value,
            'status' => $delivery->getStatus()->value,
            'attempts' => $delivery->getAttempts(),
            'subject' => $delivery->getSubject(),
            'nextAttemptAt' => self::formatDate($delivery->getNextAttemptAt()),
            'sendingAt' => self::formatDate($delivery->getSendingAt()),
            'lastError' => $delivery->getLastError(),
            'sentAt' => self::formatDate($delivery->getSentAt()),
            'createdAt' => self::formatDate($delivery->getCreatedAt()),
            'updatedAt' => self::formatDate($delivery->getUpdatedAt()),
            'attemptHistory' => $attempts,
        ];
        if ($withBody) {
            $attributes['body'] = $delivery->getBody();
        }

        return new DeliveryResource(id: (string)$delivery->getId(), attributes: $attributes);
    }

    private static function formatDate(?DateTimeImmutable $date): ?string
    {
        return $date?->format(\DATE_ATOM);
    }
}
