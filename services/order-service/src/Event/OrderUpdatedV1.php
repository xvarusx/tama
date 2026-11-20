<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Order;
use DateTimeImmutable;

/**
 * Contract v1 of order.updated.
 */
final class OrderUpdatedV1
{
    public const string TYPE = 'order.updated';
    public const int VERSION = 1;

    private function __construct(
        private Order $order,
    ) {}

    public static function create(Order $order): self
    {
        return new self($order);
    }

    public function toOutbox(): OutboxEvent
    {
        $lines = [];
        foreach ($this->order->getLines() as $line) {
            $lines[] = [
                'lineId' => (string) $line->getId(),
                'productId' => (string) $line->getProductId(),
                'productName' => $line->getProductName(),
                'sku' => $line->getSku(),
                'quantity' => $line->getQuantity(),
                'quantityProcessed' => $line->getQuantityProcessed(),
                'unitPrice' => $line->getUnitPrice(),
            ];
        }

        return new OutboxEvent(self::TYPE, self::VERSION, [
            'orderId' => (string) $this->order->getUuid(),
            'orderNumber' => $this->order->getNumber(),
            'type' => $this->order->getType()->value,
            'status' => $this->order->getStatus()->value,
            'partyId' => $this->order->getPartyId(),
            'partyName' => $this->order->getPartyName(),
            'currency' => $this->order->getCurrency(),
            'totalAmount' => $this->order->getTotalAmount(),
            'totalProcessed' => $this->order->getTotalProcessed(),
            'lineCount' => count($this->order->getLines()),
            'lines' => $lines,
            'occurredAt' => (new DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}