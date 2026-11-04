<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Full replacement of the product's own fields (PUT): anything left out of the body is cleared
 * (description, category). Variants and prices are not part of this request.
 */
final class UpdateProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        #[Assert\Positive]
        public readonly int $brandId,
        #[Assert\Positive]
        public readonly int $unitId,
        #[Assert\Length(max: 5000)]
        public readonly ?string $description = null,
        #[Assert\Positive]
        public readonly ?int $categoryId = null,
    ) {
    }
}
