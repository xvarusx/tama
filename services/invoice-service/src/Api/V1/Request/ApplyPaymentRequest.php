<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApplyPaymentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $paymentId,
        #[Assert\Positive]
        public int $amount,
        #[Assert\NotBlank]
        #[Assert\Currency]
        public string $currency,
    ) {}

    public function toPaymentId(): Uuid
    {
        return Uuid::fromString($this->paymentId);
    }
}
