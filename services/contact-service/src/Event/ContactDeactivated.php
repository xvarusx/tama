<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\ContactType;
use Symfony\Component\Uid\Uuid;

/** Published when a contact is archived. */
final readonly class ContactDeactivated
{
    public function __construct(
        public Uuid $contactId,
        public ContactType $contactType,
    ) {
    }
}
