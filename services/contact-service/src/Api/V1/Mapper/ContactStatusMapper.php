<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\ContactStatusResource;
use App\Entity\ContactStatus;

final class ContactStatusMapper
{
    public function toResource(ContactStatus $status): ContactStatusResource
    {
        return new ContactStatusResource(
            id: $status->getId()->toRfc4122(),
            attributes: [
                'name' => $status->getName(),
                'code' => $status->getCode(),
                'description' => $status->getDescription(),
            ],
        );
    }

    /** @return ContactStatusResource[] */
    public function toResourceCollection(iterable $statuses): array
    {
        return array_map($this->toResource(...), iterator_to_array($statuses));
    }
}
