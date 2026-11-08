<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Entity\Brand;
use App\Entity\Product;
final class BrandMapper
{
    public function __construct(
        private readonly ProductMapper $productMapper
    ) {}
    public function toResource(Brand $brand): array
    {

        return [
            'id' => (int) $brand->getId(),
            'attributes' => [
                'name' => $brand->getName(),
                'slug' => $brand->getSlug(),
                'products' => [array_map(fn(Product $p) => $this->productMapper->toResource($p), $brand->getProducts()->toArray())],
                'createdAt' => $brand->getCreatedAt()?->format(\DATE_ATOM),
                'updatedAt' => $brand->getUpdatedAt()?->format(\DATE_ATOM),
            ],
        ];
    }
}
