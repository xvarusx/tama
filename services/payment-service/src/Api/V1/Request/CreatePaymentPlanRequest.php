<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePaymentPlanRequest
{
    /**
     * @param InstallmentRequest[] $installments
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $invoiceId,
        
        #[Assert\Positive]
        public int $totalAmount,
        
        #[Assert\Length(exactly: 3)]
        public string $currency,
        
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $installments = [],
    ) {}
}