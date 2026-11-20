<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InvoiceLineRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $productId,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $description,
        #[Assert\Positive]
        public int $quantity,
        #[Assert\PositiveOrZero]
        public int $unitPrice,
    ) {}
}
