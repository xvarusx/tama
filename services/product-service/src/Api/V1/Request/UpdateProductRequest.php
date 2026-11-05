<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Full replacement of the product's fields (PUT): anything left out of the body is
 * cleared (description, category). When `variants` is provided it replaces the
 * product's variants: entries with an id are updated, entries without an id are
 * created, and existing variants not present in the list are deleted. Omitting
 * `variants` leaves the variants untouched (backwards compatible with the v1 API).
 */
final class UpdateProductRequest
{
    /** @param UpdateVariantRequest[]|null $variants */
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
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\Valid]
        public readonly ?array $variants = null,
    ) {}
}
