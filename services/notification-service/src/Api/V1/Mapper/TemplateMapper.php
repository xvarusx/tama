<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\TemplateResource;
use App\Entity\NotificationTemplate;

final class TemplateMapper
{
    public function toResource(NotificationTemplate $template): TemplateResource
    {
        return new TemplateResource(
            id: (string)$template->getId(),
            attributes: [
                'type' => $template->getType(),
                'channel' => $template->getChannel()->value,
                'locale' => $template->getLocale(),
                'subject' => $template->getSubject(),
                'bodyTemplate' => $template->getBodyTemplate(),
            ],
        );
    }
}
