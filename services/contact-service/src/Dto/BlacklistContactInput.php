<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Body of POST /api/contact/{id}/blacklist */
final class BlacklistContactInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'A reason is required to blacklist a contact.')]
        #[Assert\Length(max: 2000)]
        public readonly string $reason = '',
    ) {
    }
}
