<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\Channel;
use Symfony\Component\Validator\Constraints as Assert;

/** Create or replace the rendered content of a notification template. */
final readonly class SaveTemplateRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $type,
        public Channel $channel,
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 5)]
        public string $locale,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $subject,
        #[Assert\NotBlank]
        public string $bodyTemplate,
    ) {}
}
