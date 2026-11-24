<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Contact;

/** Published when a contact is archived. */
final readonly class ContactDeactivated
{
    public function __construct(
        public Contact $contact,
    ) {}
}
