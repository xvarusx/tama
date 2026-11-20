<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Product;
use App\Entity\ProductVariant;
use DateTimeImmutable;

/**
 * Contract v1 of product.created. Only ADD fields, never remove or rename them.
 * costPrice is deliberately NOT part of the event.
 */
final class ProductCreatedV1
{
    public const string TYPE = 'product.created';
    public const int VERSION = 1;

    public static function toOutbox(Product $product): OutboxEvent
    {
        $category = $product->getCategory();

        return new OutboxEvent(self::TYPE, self::VERSION, [
            'productId' => $product->getId()->toRfc4122(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'brand' => ['id' => $product->getBrand()->getId()->toRfc4122(), 'name' => $product->getBrand()->getName()],
            'unit' => ['id' => $product->getUnit()->getId()->toRfc4122(), 'code' => $product->getUnit()->getCode()],
            'category' => $category ? ['id' => $category->getId()->toRfc4122(), 'name' => $category->getName()] : null,
            'variants' => array_values(array_map(
                static fn(ProductVariant $v): array => [
                    'variantId' => $v->getId()->toRfc4122(),
                    'sku' => $v->getSku(),
                    'barcode' => $v->getBarcode(),
                    'options' => $v->getOptions(),
                    'sellingPrice' => $v->getSellingPrice(),
                ],
                $product->getProductVariant()->toArray(),
            )),
            'occurredAt' => new DateTimeImmutable()->format(\DATE_ATOM),
        ]);
    }
}
