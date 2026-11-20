<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Contact;

/** Published when a client or supplier is blacklisted. */
final readonly class ContactBlacklisted
{
    public function __construct(
        public Contact $contact,
        public string $reason,
    ) {}
}
