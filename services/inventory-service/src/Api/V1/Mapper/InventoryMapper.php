<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\InventoryMovementResource;
use App\Api\V1\Resource\InventoryResource;
use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Utils\Decimal;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class InventoryMapper
{
    public function toResource(Inventory $inventory): InventoryResource
    {
        return new InventoryResource(
            id: $inventory->getId()->toRfc4122(),
            attributes: [
                'productId' => $inventory->getProductId()?->toRfc4122(),
                'quantityOnHand' => Decimal::format($inventory->getQuantityOnHand() ?? '0.000'),
                'quantityReserved' => Decimal::format($inventory->getQuantityReserved() ?? '0.000'),
                'reorderLevel' => Decimal::format($inventory->getReorderLevel() ?? '0.000'),
                'availableQuantity' => Decimal::format($inventory->getAvailableQuantity()),
                'lowStock' => $inventory->isLowStock(),
                'version' => $inventory->getVersion(),
                'createdAt' => $inventory->getCreatedAt()?->format(\DATE_ATOM),
                'updatedAt' => $inventory->getUpdatedAt()?->format(\DATE_ATOM),
                'deletedAt' => $inventory->getDeletedAt()?->format(\DATE_ATOM),
            ],
        );
    }

    public function toMovementResource(InventoryMovement $movement): InventoryMovementResource
    {
        $inventory = $movement->getInventory();

        return new InventoryMovementResource(
            id: $movement->getId()->toRfc4122(),
            type: 'movement',
            attributes: [
                'inventoryId' => $inventory?->getId()->toRfc4122(),
                'productId' => $movement->getProductId()?->toRfc4122(),
                'type' => $movement->getType()?->value,
                'quantity' => Decimal::format($movement->getQuantity() ?? '0.000'),
                'quantityAfter' => $movement->getQuantityAfter() !== null ? Decimal::format($movement->getQuantityAfter()) : null,
                'quantityDelta' => $movement->getQuantityDelta() !== null ? Decimal::format($movement->getQuantityDelta()) : null,
                'reservedDelta' => $movement->getReservedDelta() !== null ? Decimal::format($movement->getReservedDelta()) : null,
                'reservedAfter' => $movement->getReservedAfter() !== null ? Decimal::format($movement->getReservedAfter()) : null,
                'referenceType' => $movement->getReferenceType(),
                'referenceId' => $movement->getReferenceId(),
                'reason' => $movement->getReason(),
                'createdAt' => $movement->getCreatedAt()?->format(\DATE_ATOM),
            ],
        );
    }
}
