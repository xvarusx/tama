<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Contact;

/**
 * Published when a contact's regular fields are updated (not status changes).
 * Contract v1 of contact.updated. Only ADD fields, never remove or rename them.
 */
final readonly class ContactUpdated
{
    public const string TYPE = 'contact.updated';
    public const int VERSION = 1;

    /**
     * @param array<string> $changedFields List of field names that were changed
     */
    public function __construct(
        public Contact $contact,
        public array $changedFields,
    ) {}

    public static function fromContact(Contact $contact, array $changedFields): self
    {
        return new self($contact, $changedFields);
    }

    public static function toOutbox(Contact $contact, array $changedFields): \App\Entity\OutboxEvent
    {
        return new \App\Entity\OutboxEvent(self::TYPE, self::VERSION, [
            'contactId' => $contact->getId()->toRfc4122(),
            'name' => $contact->getName(),
            'type' => $contact->getContactType()->value,
            'changedFields' => $changedFields,
        ]);
    }
}
