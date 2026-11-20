<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\Channel;
use Symfony\Component\Validator\Constraints as Assert;

/** One row of a preference-save request. */
final readonly class PreferenceRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $type,
        public Channel $channel,
        public bool $enabled,
    ) {}
}
