<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Message created from Order Service's order.shipped event.
 * Inventory Service consumes this to record outgoing stock.
 */
final readonly class OrderShippedV1
{
    /**
     * @param array<int, array{productId: string, quantity: int}> $lineItems
     */
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public string $customerName,
        public array $lineItems,
    ) {}
}
