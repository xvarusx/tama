<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShipOrderRequest
{
    /**
     * @param array<int, array{quantity: int}> $quantities Map of lineId => quantity
     */
    public function __construct(
        #[Assert\Valid]
        public array $quantities,

        #[Assert\Length(max: 5000)]
        public ?string $notes = null,

        #[Assert\Length(max: 36)]
        public ?string $trackingNumber = null,
    ) {}
}