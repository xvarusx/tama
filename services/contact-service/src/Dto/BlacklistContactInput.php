<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Payload for the "blacklist a contact" state transition. */
final readonly class BlacklistContactInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $reason,
    ) {}
}
