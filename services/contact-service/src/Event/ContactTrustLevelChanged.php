<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\ContactType;
use App\Enum\TrustLevel;
use Symfony\Component\Uid\Uuid;

/** Published when the trust level of a contact changes. */
final readonly class ContactTrustLevelChanged
{
    public function __construct(
        public Uuid $contactId,
        public ContactType $contactType,
        public TrustLevel $trustLevel,
    ) {
    }
}
