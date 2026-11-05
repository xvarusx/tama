<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
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
        public string $name,
        #[Assert\Positive]
        public int $brandId,
        #[Assert\Positive]
        public int $unitId,
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\Valid]
        public array $variants,
        #[Assert\Length(max: 5000)]
        public ?string $description = null,
        #[Assert\Positive]
        public ?int $categoryId = null,
    ) {}
}
