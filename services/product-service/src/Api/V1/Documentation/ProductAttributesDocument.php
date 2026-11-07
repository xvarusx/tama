<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The attributes block of a product resource. */
final readonly class ProductAttributesDocument
{
    /**
     * @param list<VariantDocument> $variants
     * @param list<MediaDocument>   $media
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public BrandRefDocument $brand,
        public UnitRefDocument $unit,
        public ?CategoryRefDocument $category,
        public array $variants,
        public array $media,
        /** ISO-8601 timestamp. */
        public ?string $createdAt,
        /** ISO-8601 timestamp. */
        public ?string $updatedAt,
    ) {}
}
