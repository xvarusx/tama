<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SavePreferencesRequest
{
    /**
     * @param list<PreferenceRequest> $preferences
     */
    public function __construct(
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        #[Assert\Unique(fields: ['type', 'channel'])]
        public array $preferences,
    ) {}
}
