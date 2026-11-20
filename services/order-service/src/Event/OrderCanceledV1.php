<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Order;
use App\Enum\OrderStatus;
use DateTimeImmutable;

/**
 * Contract v1 of order.canceled.
 */
final class OrderCanceledV1
{
    public const string TYPE = 'order.canceled';
    public const int VERSION = 1;

    private function __construct(
        private Order $order,
        private OrderStatus $previousStatus,
    ) {}

    public static function create(Order $order, OrderStatus $previousStatus): self
    {
        return new self($order, $previousStatus);
    }

    public function toOutbox(): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'orderId' => (string) $this->order->getUuid(),
            'orderNumber' => $this->order->getNumber(),
            'type' => $this->order->getType()->value,
            'status' => $this->order->getStatus()->value,
            'previousStatus' => $this->previousStatus->value,
            'reason' => 'User requested cancellation',
            'occurredAt' => (new DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}