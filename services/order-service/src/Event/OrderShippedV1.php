<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Order;
use DateTimeImmutable;

/**
 * Contract v1 of order.shipped.
 */
final class OrderShippedV1
{
    public const string TYPE = 'order.shipped';
    public const int VERSION = 1;

    /**
     * @param array<int, array{lineId: int, quantity: int}> $quantities
     */
    private function __construct(
        private Order $order,
        private array $quantities,
    ) {}

    /**
     * @param array<int, array{lineId: int, quantity: int}> $quantities
     */
    public static function create(Order $order, array $quantities): self
    {
        return new self($order, $quantities);
    }

    public function toOutbox(): OutboxEvent
    {
        $shippedLines = [];
        foreach ($this->quantities as $lineId => $quantity) {
            $shippedLines[] = [
                'lineId' => $lineId,
                'quantity' => $quantity,
            ];
        }

        return new OutboxEvent(self::TYPE, self::VERSION, [
            'orderId' => (string) $this->order->getUuid(),
            'orderNumber' => $this->order->getNumber(),
            'customerName' => $this->order->getPartyName(),
            'status' => $this->order->getStatus()->value,
            'totalProcessed' => $this->order->getTotalProcessed(),
            'shippedLines' => $shippedLines,
            'occurredAt' => (new DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}