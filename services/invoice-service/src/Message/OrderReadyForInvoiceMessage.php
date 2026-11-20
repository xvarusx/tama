<?php

declare(strict_types=1);

namespace App\Message;

/**
 * An order became invoiceable. Produced by the order service when goods ship
 * or a purchase order is received; consumed here to create the draft invoice.
 */
final readonly class OrderReadyForInvoiceMessage
{
    /**
     * @param list<array{productId: string, description: string, quantity: int, unitPrice: int}> $lines
     */
    public function __construct(
        public string $eventType,
        public string $orderId,
        public string $type,
        public string $partyId,
        public string $partyName,
        public string $currency,
        public array $lines,
        public string $occurredAt,
    ) {}
}
