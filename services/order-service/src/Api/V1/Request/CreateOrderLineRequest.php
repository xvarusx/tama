<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderLineRequest
{
    /**
     * @param CreateOrderLineRequest[] $lines
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 36)]
        public string $productId,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $productName,

        #[Assert\Positive]
        public int $quantity,

        #[Assert\Positive]
        public int $unitPrice,

        #[Assert\Length(max: 50)]
        public ?string $sku = null,
    ) {}
}