<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInventoryRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $productId,
        // Opening stock: recorded as an InitialStock movement, not set directly.
        #[Assert\Type('numeric')]
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual('999999999.999')]
        public float|string|null $quantityOnHand = null,
        #[Assert\Type('numeric')]
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual('999999999.999')]
        public float|string|null $reorderLevel = null,
    ) {}
}
