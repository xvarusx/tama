<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

use App\Enum\InventoryMovementType;
use DateTimeImmutable;

/** A single inventory movement as returned by the API. */
final readonly class InventoryMovementDocument
{
    public function __construct(
        public string $id,
        public string $productId,
        public InventoryMovementType $type,
        public string $quantity,
        public DateTimeImmutable $createdAt,
        public ?string $quantityAfter = null,
        public ?string $quantityDelta = null,
        public ?string $reservedDelta = null,
        public ?string $reservedAfter = null,
        public ?string $referenceType = null,
        public ?string $referenceId = null,
        public ?string $reason = null,
    ) {}
}
