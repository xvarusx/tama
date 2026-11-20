<?php

namespace App\Service;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Enum\InventoryMovementType;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventoryRepository;
use App\Utils\Decimal;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class StockMovementService
{
    private const string ZERO = '0.000';
    private const int SCALE = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private InventoryRepository $inventories,
        private InventoryMovementRepository $movements,
    ) {}

    public function createInventory(string $productId, string $initialQuantity, string $reorderLevel): Inventory
    {
        return $this->em->wrapInTransaction(function () use ($productId, $initialQuantity, $reorderLevel): Inventory {
            $inventory = $this->inventories->findByProductId($productId);

            if ($inventory !== null) {
                // Two concurrent "restore" requests must not both add opening stock.
                $this->em->refresh($inventory, LockMode::PESSIMISTIC_WRITE);
            }

            if ($inventory !== null && $inventory->getDeletedAt() === null) {
                throw new ConflictHttpException('Inventory already exists for this product.');
            }

            $now = new DateTimeImmutable();

            if ($inventory === null) {
                $inventory = new Inventory()
                    ->setProductId(Uuid::fromString($productId))
                    ->setQuantityOnHand(self::ZERO)
                    ->setQuantityReserved(self::ZERO)
                    ->setCreatedAt($now);
                $this->em->persist($inventory);
            }

            $inventory
                ->setReorderLevel($reorderLevel)
                ->setDeletedAt(null)
                ->setUpdatedAt($now);

            if (Decimal::compare($initialQuantity, self::ZERO) > 0) {
                $this->record(
                    $inventory,
                    InventoryMovementType::ADJUSTMENT,
                    $initialQuantity,
                    'inventory',
                    null,
                    'Initial stock',
                );
            }

            return $inventory;
        });
    }

    public function updateSettings(string $id, string $reorderLevel, int $expectedVersion): Inventory
    {
        $inventory = $this->inventories->findActive($id)
            ?? throw new NotFoundHttpException('Inventory item not found');

        $this->em->lock($inventory, LockMode::OPTIMISTIC, $expectedVersion);

        $inventory
            ->setReorderLevel($reorderLevel)
            ->setUpdatedAt(new DateTimeImmutable());

        $this->em->flush();

        return $inventory;
    }

    public function softDelete(Inventory $inventory): void
    {
        if (Decimal::compare($inventory->getQuantityOnHand() ?? self::ZERO, self::ZERO) !== 0
            || Decimal::compare($inventory->getQuantityReserved() ?? self::ZERO, self::ZERO) !== 0) {
            throw new ConflictHttpException('Inventory still has stock or reservations. Write it off before deleting.');
        }

        $now = new DateTimeImmutable();
        $this->record(
            $inventory,
            InventoryMovementType::ADJUSTMENT,
            self::ZERO,
            'inventory',
            null,
            'Inventory deleted',
        );
        $inventory->setDeletedAt($now)->setUpdatedAt($now);

        $this->em->flush();
    }

    /**
     * Receive stock - increases inventory (IN movement).
     * Used when goods are received (purchase order received event).
     */
    public function receiveStock(string $inventoryId, string $quantity, ?string $referenceType, ?string $referenceId, ?string $reason = null): AppliedMovement
    {
        if (Decimal::compare($quantity, self::ZERO) <= 0) {
            throw new UnprocessableEntityHttpException('Quantity must be greater than zero for receiving stock.');
        }

        return $this->em->wrapInTransaction(function () use ($inventoryId, $quantity, $referenceType, $referenceId, $reason): AppliedMovement {
            $inventory = $this->em->find(Inventory::class, Uuid::fromString($inventoryId))
                ?? throw new NotFoundHttpException('Inventory item not found');

            $this->em->refresh($inventory, LockMode::PESSIMISTIC_WRITE);

            if ($inventory->getDeletedAt() !== null) {
                throw new NotFoundHttpException('Inventory item not found');
            }

            // Check idempotency
            if ($referenceType !== null && $referenceId !== null) {
                $existing = $this->movements->findOneBy([
                    'inventory' => $inventory,
                    'type' => InventoryMovementType::IN,
                    'referenceType' => $referenceType,
                    'referenceId' => $referenceId,
                ]);

                if ($existing !== null) {
                    return new AppliedMovement($existing, false);
                }
            }

            return new AppliedMovement(
                $this->record(
                    $inventory,
                    InventoryMovementType::IN,
                    $quantity,
                    $referenceType,
                    $referenceId,
                    $reason,
                ),
                true,
            );
        });
    }

    /**
     * Issue stock - decreases inventory (OUT movement).
     * Used when items are shipped (order shipped event).
     */
    public function issueStock(string $inventoryId, string $quantity, ?string $referenceType, ?string $referenceId, ?string $reason = null): AppliedMovement
    {
        if (Decimal::compare($quantity, self::ZERO) <= 0) {
            throw new UnprocessableEntityHttpException('Quantity must be greater than zero for issuing stock.');
        }

        return $this->em->wrapInTransaction(function () use ($inventoryId, $quantity, $referenceType, $referenceId, $reason): AppliedMovement {
            $inventory = $this->em->find(Inventory::class, Uuid::fromString($inventoryId))
                ?? throw new NotFoundHttpException('Inventory item not found');

            $this->em->refresh($inventory, LockMode::PESSIMISTIC_WRITE);

            if ($inventory->getDeletedAt() !== null) {
                throw new NotFoundHttpException('Inventory item not found');
            }

            // Check idempotency
            $existing = $this->movements->findOneBy([
                'inventory' => $inventory,
                'type' => InventoryMovementType::OUT,
                'referenceType' => $referenceType,
                'referenceId' => $referenceId,
            ]);

            if ($existing !== null) {
                return new AppliedMovement($existing, false);
            }

            $currentQuantity = $inventory->getQuantityOnHand() ?? self::ZERO;

            if (Decimal::compare($currentQuantity, $quantity) < 0) {
                throw new ConflictHttpException('Insufficient stock on hand.');
            }

            return new AppliedMovement(
                $this->record(
                    $inventory,
                    InventoryMovementType::OUT,
                    bcmul($quantity, '-1', self::SCALE),
                    $referenceType,
                    $referenceId,
                    $reason,
                ),
                true,
            );
        });
    }

    /**
     * Adjust stock - manual adjustment (ADJUSTMENT movement).
     * Used for recounts, write-offs, etc.
     */
    public function adjustStock(string $inventoryId, string $delta, ?string $referenceType, ?string $referenceId, ?string $reason = null): AppliedMovement
    {
        if (Decimal::compare($delta, self::ZERO) === 0) {
            throw new UnprocessableEntityHttpException('Adjustment quantity cannot be zero.');
        }

        return $this->em->wrapInTransaction(function () use ($inventoryId, $delta, $referenceType, $referenceId, $reason): AppliedMovement {
            $inventory = $this->em->find(Inventory::class, Uuid::fromString($inventoryId))
                ?? throw new NotFoundHttpException('Inventory item not found');

            $this->em->refresh($inventory, LockMode::PESSIMISTIC_WRITE);

            if ($inventory->getDeletedAt() !== null) {
                throw new NotFoundHttpException('Inventory item not found');
            }

            // Check idempotency
            $existing = $this->movements->findOneBy([
                'inventory' => $inventory,
                'type' => InventoryMovementType::ADJUSTMENT,
                'referenceType' => $referenceType,
                'referenceId' => $referenceId,
            ]);

            if ($existing !== null) {
                return new AppliedMovement($existing, false);
            }

            $currentQuantity = $inventory->getQuantityOnHand() ?? self::ZERO;
            $newQuantity = bcadd($currentQuantity, $delta, self::SCALE);

            if (Decimal::compare($newQuantity, self::ZERO) < 0) {
                throw new ConflictHttpException('Resulting stock cannot be negative.');
            }

            return new AppliedMovement(
                $this->record(
                    $inventory,
                    InventoryMovementType::ADJUSTMENT,
                    $delta,
                    $referenceType,
                    $referenceId,
                    $reason,
                ),
                true,
            );
        });
    }

    /**
     * Legacy method - kept for backward compatibility.
     * Applies a movement and updates inventory atomically.
     */
    public function apply(
        string $inventoryId,
        InventoryMovementType $type,
        string $quantity,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
    ): AppliedMovement {
        $isZero = Decimal::compare($quantity, self::ZERO) === 0;

        // IN movements: receipt, incoming
        if ($type === InventoryMovementType::IN) {
            if ($isZero) {
                throw new UnprocessableEntityHttpException('Quantity must be greater than zero.');
            }

            return $this->receiveStock($inventoryId, $quantity, $referenceType ?? 'receipt', $referenceId, $reason);
        }

        // ADJUSTMENT movements
        if ($type === InventoryMovementType::ADJUSTMENT) {
            if ($isZero) {
                throw new UnprocessableEntityHttpException('Adjustment quantity cannot be zero.');
            }

            return $this->adjustStock($inventoryId, $quantity, $referenceType ?? 'adjustment', $referenceId, $reason);
        }

        // OUT movements: sale, outgoing
        return $this->issueStock(
            $inventoryId,
            $quantity,
            $referenceType ?? 'sale',
            $referenceId,
            $reason,
        );
    }

    private function record(
        Inventory $inventory,
        InventoryMovementType $type,
        string $delta,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
    ): InventoryMovement {
        // Positive delta for IN, negative for OUT/ADJUSTMENT
        $quantityAfter = Decimal::format(bcadd($inventory->getQuantityOnHand() ?? self::ZERO, $delta, self::SCALE));

        if (Decimal::compare($quantityAfter, self::ZERO) < 0) {
            throw new ConflictHttpException('Cannot have negative stock on hand.');
        }

        $now = new DateTimeImmutable();

        // quantity stores absolute value, quantityDelta stores signed delta
        $quantity = Decimal::format(abs((float)$delta));

        $movement = new InventoryMovement()
            ->setType($type)
            ->setQuantity($quantity)
            ->setQuantityAfter($quantityAfter)
            ->setQuantityDelta(Decimal::format($delta))
            ->setReservedDelta(self::ZERO)
            ->setReservedAfter($inventory->getQuantityReserved() ?? self::ZERO)
            ->setReferenceType($referenceType)
            ->setReferenceId($referenceId)
            ->setReason($reason)
            ->setCreatedAt($now);

        $inventory
            ->setQuantityOnHand($quantityAfter)
            ->setUpdatedAt($now);

        $inventory->addMovement($movement);
        $this->em->persist($movement);

        return $movement;
    }
}
