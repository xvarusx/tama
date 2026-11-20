<?php

declare(strict_types=1);

namespace App\Handler\Contact;

use App\Api\V1\Request\UpdateContactRequest;
use App\Entity\Contact;
use App\Entity\ContactAuditEntry;
use App\Entity\OutboxEvent;
use App\Event\ContactBlacklisted;
use App\Event\ContactDeactivated;
use App\Event\ContactReinstated;
use App\Event\ContactUpdated;
use App\Exceptions\ContactNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpdateContactHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {}

    public function __invoke(Uuid $id, UpdateContactRequest $payload, Uuid $userId): Contact
    {
        $contact = $this->em->find(Contact::class, $id);
        if (!$contact) {
            throw new ContactNotFoundException((string) $id);
        }

        try {
            $this->serializer->deserialize(
                json_encode($payload),
                Contact::class,
                'json',
                ['object_to_populate' => $contact],
            );
        } catch (NotEncodableValueException $e) {
            throw new \InvalidArgumentException('Request body must be valid JSON.', 0, $e);
        }

        $violations = $this->validator->validate($contact);
        if (count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations);
        }

        $contact->markUpdatedBy($userId);
        $contact->emitUpdatedEvent();
        $this->em->persist(new ContactAuditEntry($contact->getId(), $userId, 'updated'));

        // Persist outbox events for domain events
        foreach ($contact->pullEvents() as $event) {
            if ($event instanceof ContactBlacklisted) {
                $this->em->persist(OutboxEvent::fromEvent($event));
            } elseif ($event instanceof ContactDeactivated) {
                $this->em->persist(OutboxEvent::fromEvent($event));
            } elseif ($event instanceof ContactReinstated) {
                $this->em->persist(OutboxEvent::fromEvent($event));
            } elseif ($event instanceof ContactUpdated) {
                $this->em->persist(OutboxEvent::fromEvent($event));
            }
        }

        $this->em->flush();

        return $contact;
    }
}
