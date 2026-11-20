<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

use DateTimeImmutable;

/** A single inventory item as returned by the API. */
final readonly class InventoryDocument
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $quantityOnHand,
        public string $quantityReserved,
        public string $reorderLevel,
        public string $availableQuantity,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?string $deletedAt = null,
    ) {}
}
