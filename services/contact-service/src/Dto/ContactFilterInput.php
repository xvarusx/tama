<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ContactStatus;
use App\Enum\ContactType;
use App\Enum\TrustLevel;

final class ContactFilterInput
{
    public function __construct(
        public readonly ?ContactType $contactType = null,
        public readonly ?ContactStatus $contactStatus = null,
        public readonly ?TrustLevel $trustLevel = null,
    ) {
    }
}
