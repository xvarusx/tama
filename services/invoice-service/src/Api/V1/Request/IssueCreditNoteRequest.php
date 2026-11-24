<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssueCreditNoteRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $amount,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $reason,
    ) {}
}
