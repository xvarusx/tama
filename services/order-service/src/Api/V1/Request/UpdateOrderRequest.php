<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateOrderRequest
{
    /**
     * @param CreateOrderLineRequest[] $lines
     */
    public function __construct(
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $lines,

        #[Assert\Length(max: 5000)]
        public ?string $notes = null,

        #[Assert\Length(max: 36)]
        public ?string $invoiceId = null,
    ) {}
}