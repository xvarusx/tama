<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Entity\Category;
use App\Entity\Product;
final class CategoryMapper
{
    public function __construct(
        private readonly ProductMapper $productMapper
    ) {}
    public function toResource(Category $category): array
    {

        return [
            'id' => (int) $category->getId(),
            'attributes' => [
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'products' => [array_map(fn(Product $p) => $this->productMapper->toResource($p), $category->getProducts()->toArray())],
                'createdAt' => $category->getCreatedAt()?->format(\DATE_ATOM),
                'updatedAt' => $category->getUpdatedAt()?->format(\DATE_ATOM),
            ],
        ];
    }
}
