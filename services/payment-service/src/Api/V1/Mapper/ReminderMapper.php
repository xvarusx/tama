<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Documentation\ReminderAttributesDocument;
use App\Api\V1\Documentation\ReminderDocument;
use App\Api\V1\Resource\ReminderResource;
use App\Entity\Reminder;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class ReminderMapper
{
    public function toResource(Reminder $reminder): ReminderResource
    {
        return new ReminderResource(
            id: $reminder->getId()->toRfc4122(),
            attributes: [
                'installmentId' => $reminder->getInstallment()->getId()->toRfc4122(),
                'kind' => $reminder->getKind()->value,
                'scheduledFor' => $reminder->getScheduledFor()->format(\DATE_ATOM),
                'status' => $reminder->getStatus(),
                'sentAt' => $reminder->getSentAt()?->format(\DATE_ATOM),
            ],
        );
    }

    public function toDocument(Reminder $reminder): ReminderDocument
    {
        return new ReminderDocument(
            id: $reminder->getId()->toRfc4122(),
            attributes: new ReminderAttributesDocument(
                installmentId: $reminder->getInstallment()->getId()->toRfc4122(),
                kind: $reminder->getKind()->value,
                scheduledFor: $reminder->getScheduledFor()->format(\DATE_ATOM),
                status: $reminder->getStatus(),
                sentAt: $reminder->getSentAt()?->format(\DATE_ATOM),
            ),
        );
    }
}