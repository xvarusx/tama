<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\ContactResource;
use App\Entity\Contact;

final class ContactMapper
{
    public function toResource(Contact $contact): ContactResource
    {
        return new ContactResource(
            id: $contact->getId()->toRfc4122(),
            type: 'contact',
            attributes: [
                'name' => $contact->getName(),
                'email' => $contact->getEmail(),
                'phone' => $contact->getPhone(),
                'address' => $contact->getAddress(),
                'taxNumber' => $contact->getTaxNumber(),
                'contactType' => $contact->getContactType()->value,
                'status' => $contact->getStatus()->value,
                'createdAt' => $contact->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $contact->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
                'createdBy' => $contact->getCreatedBy()?->toRfc4122(),
                'updatedBy' => $contact->getUpdatedBy()?->toRfc4122(),
            ],
        );
    }
}
