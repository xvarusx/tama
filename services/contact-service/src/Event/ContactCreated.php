<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Contact;
use App\Entity\OutboxEvent;

/**
 * Contract v1 of contact.created. Only ADD fields, never remove or rename them.
 */
final class ContactCreated
{
    public const string TYPE = 'contact.created';
    public const int VERSION = 1;

    private function __construct() {}

    public static function fromContact(Contact $contact): self
    {
        return new self();
    }

    public static function toOutbox(Contact $contact): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'contactId' => $contact->getId()->toRfc4122(),
            'name' => $contact->getName(),
            'type' => $contact->getContactType()->value,
        ]);
    }
}
