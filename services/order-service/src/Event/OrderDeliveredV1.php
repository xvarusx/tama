<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Order;
use DateTimeImmutable;

/**
 * Contract v1 of order.delivered.
 */
final class OrderDeliveredV1
{
    public const string TYPE = 'order.delivered';
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
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'orderId' => (string) $this->order->getUuid(),
            'orderNumber' => $this->order->getNumber(),
            'customerName' => $this->order->getPartyName(),
            'status' => $this->order->getStatus()->value,
            'totalProcessed' => $this->order->getTotalProcessed(),
            'occurredAt' => (new DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}