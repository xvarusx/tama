<?php

declare(strict_types=1);

namespace App\Handler\Contact;

use App\Api\V1\Request\CreateContactRequest;
use App\Dto\CreateContactInput;
use App\Entity\Contact;
use App\Entity\ContactAuditEntry;
use App\Entity\OutboxEvent;
use App\Event\ContactCreated;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Use case: create a contact.
 * The contact and its outbox event are saved in ONE transaction.
 */
final readonly class CreateContactHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(CreateContactRequest $payload, Uuid $userId): Contact
    {
        $input = new CreateContactInput(
            name: $payload->name,
            contactType: $payload->type,
            email: $payload->email,
            phone: $payload->phone,
            address: $payload->address,
            taxNumber: $payload->taxNumber,
        );

        $contact = new Contact($input->name, $userId, $input->contactType);
        $contact->setEmail($input->email);
        $contact->setPhone($input->phone);
        $contact->setAddress($input->address);
        $contact->setTaxNumber($input->taxNumber);

        try {
            $this->em->wrapInTransaction(function () use ($contact, $userId): void {
                $this->em->persist($contact);
                $this->em->flush(); // persists the contact before its audit entry and outbox event

                $this->em->persist(new ContactAuditEntry($contact->getId(), $userId, 'created'));
                $this->em->persist(ContactCreated::toOutbox($contact));
            });
        } catch (UniqueConstraintViolationException) {
            throw new \InvalidArgumentException('A contact with this tax number already exists.');
        }

        return $contact;
    }
}
