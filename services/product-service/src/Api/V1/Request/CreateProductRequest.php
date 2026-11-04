<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateProductRequest
{
    /**
     * Every product has at least one variant; simple products get a single default variant.
     * Typed arrays of DTOs need: composer require phpdocumentor/reflection-docblock.
     *
     * @param CreateVariantRequest[] $variants
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        #[Assert\Positive]
        public readonly int $brandId,
        #[Assert\Positive]
        public readonly int $unitId,
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\Valid]
        public readonly array $variants,
        #[Assert\Length(max: 5000)]
        public readonly ?string $description = null,
        #[Assert\Positive]
        public readonly ?int $categoryId = null,
    ) {
    }
}
