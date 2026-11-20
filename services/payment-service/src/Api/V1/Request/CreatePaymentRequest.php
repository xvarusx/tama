<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\PaymentDirection;
use App\Enum\PaymentMethod;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePaymentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $number,
        
        #[Assert\NotBlank]
        public PaymentDirection $direction,
        
        #[Assert\NotBlank]
        public PaymentMethod $method,
        
        #[Assert\NotBlank]
        public string $partyId,
        
        #[Assert\NotBlank]
        public string $partyName,
        
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 3)]
        public string $currency,
        
        #[Assert\Positive]
        public int $amount,
        
        #[Assert\Length(max: 255)]
        public ?string $reference = null,
    ) {}
}