<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Event: a contract between services, ideally plain JSON on the wire.
 */
final readonly class ApplyStockMovement
{
    public function __construct(
        public string $productId,
        public string $type,          // 'reserve' | 'sale' | 'receipt' ...
        public string $quantity,
        public string $referenceType, // 'order' | 'purchase_order'
        public string $referenceId,   // e.g. order id
        public ?string $reason = null,
    ) {}
}
