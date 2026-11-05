<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Product;
use App\Entity\ProductVariant;

/**
 * Contract v1 of product.created. Only ADD fields, never remove or rename them.
 * costPrice is deliberately NOT part of the event.
 */
final class ProductCreatedV1
{
    public const TYPE = 'product.created';
    public const VERSION = 1;

    public static function toOutbox(Product $product): OutboxEvent
    {
        $category = $product->getCategory();

        return new OutboxEvent(self::TYPE, self::VERSION, [
            'productId' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'brand' => ['id' => $product->getBrand()->getId(), 'name' => $product->getBrand()->getName()],
            'unit' => ['id' => $product->getUnit()->getId(), 'code' => $product->getUnit()->getCode()],
            'category' => $category ? ['id' => $category->getId(), 'name' => $category->getName()] : null,
            'variants' => array_values(array_map(
                static fn(ProductVariant $v): array => [
                    'variantId' => $v->getId(),
                    'sku' => $v->getSku(),
                    'barcode' => $v->getBarcode(),
                    'options' => $v->getOptions(),
                    'sellingPrice' => $v->getSellingPrice(),
                ],
                $product->getProductVariant()->toArray(),
            )),
            'occurredAt' => new \DateTimeImmutable()->format(\DATE_ATOM),
        ]);
    }
}
