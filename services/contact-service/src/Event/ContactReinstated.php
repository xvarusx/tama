<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Contact;

/** Published when a blacklisted contact is made active again. */
final readonly class ContactReinstated
{
    public function __construct(
        public Contact $contact,
    ) {}
}
