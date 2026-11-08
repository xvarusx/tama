<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Entity\Brand;

final readonly class BrandMapper
{
    public function __construct(
        private ProductMapper $productMapper
    ) {}
    public function toResource(Brand $brand): array
    {

        return [
            'id' => (int)$brand->getId(),
            'attributes' => [
                'name' => $brand->getName(),
                'slug' => $brand->getSlug(),
                'products' => [array_map($this->productMapper->toResource(...), $brand->getProducts()->toArray())],
                'createdAt' => $brand->getCreatedAt()?->format(\DATE_ATOM),
                'updatedAt' => $brand->getUpdatedAt()?->format(\DATE_ATOM),
            ],
        ];
    }
}
