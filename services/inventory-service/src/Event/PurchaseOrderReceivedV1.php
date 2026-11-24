<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Message created from Order Service's purchase.order.received event.
 * Inventory Service consumes this to record incoming stock.
 */
final readonly class PurchaseOrderReceivedV1
{
    /**
     * @param array<int, array{productId: string, quantity: int}> $lineItems
     */
    public function __construct(
        public string $orderId,
        public string $orderNumber,
        public array $lineItems,
    ) {}
}
