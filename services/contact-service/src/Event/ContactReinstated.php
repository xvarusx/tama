<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\ContactType;
use Symfony\Component\Uid\Uuid;

/** Published when a blacklisted contact is made active again. */
final readonly class ContactReinstated
{
    public function __construct(
        public Uuid $contactId,
        public ContactType $contactType,
    ) {
    }
}
