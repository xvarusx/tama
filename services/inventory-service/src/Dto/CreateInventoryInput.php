<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInventoryInput
{
    public function __construct(
        #[Assert\Positive]
        public int $productId,
        // Opening stock: recorded as an InitialStock movement, not set directly.
        #[Assert\Type('numeric')]
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual('999999999.999')]
        public float|string|null $quantityOnHand = null,
        #[Assert\Type('numeric')]
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual('999999999.999')]
        public float|string|null $reorderLevel = null,
    ) {
    }
}
