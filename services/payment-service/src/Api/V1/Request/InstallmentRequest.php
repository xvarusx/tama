<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InstallmentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $dueDate,
        
        #[Assert\Positive]
        public int $amount,
    ) {}
}