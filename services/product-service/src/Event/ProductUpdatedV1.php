<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Product;
use DateTimeImmutable;

/**
 * Contract v1 of product.updated. Only ADD fields, never remove or rename them.
 * Product-level fields only: variants and prices have their own events.
 */
final class ProductUpdatedV1
{
    public const string TYPE = 'product.updated';
    public const int VERSION = 1;

    /** @param string[] $changedFields e.g. ['name', 'brand'] */
    public static function toOutbox(Product $product, array $changedFields): OutboxEvent
    {
        $category = $product->getCategory();

        return new OutboxEvent(self::TYPE, self::VERSION, [
            'productId' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'brand' => ['id' => $product->getBrand()->getId(), 'name' => $product->getBrand()->getName()],
            'unit' => ['id' => $product->getUnit()->getId(), 'code' => $product->getUnit()->getCode()],
            'category' => $category ? ['id' => $category->getId(), 'name' => $category->getName()] : null,
            'changedFields' => $changedFields,
            'occurredAt' => new DateTimeImmutable()->format(\DATE_ATOM),
        ]);
    }
}
