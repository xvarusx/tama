<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\OrderType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderRequest
{
    /**
     * @param CreateOrderLineRequest[] $lines
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 50)]
        public string $number,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: [OrderType::PURCHASE->value, OrderType::SALES->value])]
        public string $type,

        #[Assert\NotBlank]
        #[Assert\Length(max: 36)]
        public string $partyId,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $partyName,

        #[Assert\NotBlank]
        #[Assert\Length(max: 3)]
        public string $currency,

        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $lines,

        #[Assert\Length(max: 5000)]
        public ?string $notes = null,

        #[Assert\Length(max: 36)]
        public ?string $invoiceId = null,
    ) {}
}