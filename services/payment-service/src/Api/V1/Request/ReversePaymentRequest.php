<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReversePaymentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $paymentId,
        
        #[Assert\NotBlank]
        public string $reason,
    ) {}
}