<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\ContactType;
use Symfony\Component\Uid\Uuid;

/** Published when a client or supplier is blacklisted. */
final readonly class ContactBlacklisted
{
    public function __construct(
        public Uuid $contactId,
        public ContactType $contactType,
        public string $reason,
    ) {
    }
}
