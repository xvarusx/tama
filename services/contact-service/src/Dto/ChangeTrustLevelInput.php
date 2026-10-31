<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\TrustLevel;

/** Body of PATCH /api/contact/{id}/trust-level */
final class ChangeTrustLevelInput
{
    public function __construct(
        public readonly TrustLevel $trustLevel,
    ) {
    }
}
