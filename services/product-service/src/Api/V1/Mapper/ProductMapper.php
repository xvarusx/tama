<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\ProductResource;
use App\Api\V1\Resource\VariantResource;
use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductVariant;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class ProductMapper
{
    public function toResource(Product $product): ProductResource
    {
        $category = $product->getCategory();

        return new ProductResource(
            id: (int)$product->getId(),
            attributes: [
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'brand' => ['id' => $product->getBrand()->getId(), 'name' => $product->getBrand()->getName()],
                'unit' => ['id' => $product->getUnit()->getId(), 'code' => $product->getUnit()->getCode()],
                'category' => $category ? ['id' => $category->getId(), 'name' => $category->getName()] : null,
                'variants' => array_values(array_map(
                    fn(ProductVariant $v): VariantResource => $this->toVariantResource($v),
                    $product->getProductVariant()->toArray(),
                )),
                'media' => array_values(array_map(
                    fn(ProductMedia $m): array => [
                        'id' => $m->getId(),
                        // Relative to the product API base; the SPA prefixes it with /api/product.
                        'url' => '/media/' . $m->getId(),
                        'kind' => $m->getKind(),
                        'position' => $m->getPosition(),
                    ],
                    $product->getMedia()->toArray(),
                )),
                'createdAt' => $product->getCreatedAt()?->format(\DATE_ATOM),
                'updatedAt' => $product->getUpdatedAt()?->format(\DATE_ATOM),
            ],
        );
    }

    public function toVariantResource(ProductVariant $variant): VariantResource
    {
        return new VariantResource(
            id: (int)$variant->getId(),
            sku: $variant->getSku(),
            barcode: $variant->getBarcode(),
            options: $variant->getOptions(),
            sellingPrice: $variant->getSellingPrice(),
            costPrice: $variant->getCostPrice(),
        );
    }
}
