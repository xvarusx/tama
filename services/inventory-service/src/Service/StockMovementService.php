<?php

namespace App\Service;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Enum\InventoryMovementType;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventoryRepository;
use App\Util\Decimal;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class StockMovementService
{
    private const string ZERO = '0.000';
    private const int SCALE = 3;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InventoryRepository $inventories,
        private readonly InventoryMovementRepository $movements,
    ) {
    }

    public function createInventory(int $productId, string $initialQuantity, string $reorderLevel): Inventory
    {
        return $this->em->wrapInTransaction(function () use ($productId, $initialQuantity, $reorderLevel): Inventory {
            $inventory = $this->inventories->findByProductId($productId);

            if (null !== $inventory) {
                // Two concurrent "restore" requests must not both add opening stock.
                $this->em->refresh($inventory, LockMode::PESSIMISTIC_WRITE);
            }

            if (null !== $inventory && null === $inventory->getDeletedAt()) {
                throw new ConflictHttpException('Inventory already exists for this product.');
            }

            $now = new \DateTimeImmutable();

            if (null === $inventory) {
                $inventory = (new Inventory())
                    ->setProductId($productId)
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
                    InventoryMovementType::InitialStock,
                    $initialQuantity,
                    self::ZERO,
                    'inventory',
                    null,
                    'Initial stock',
                );
            }

            return $inventory;
        });
    }

    public function updateSettings(int $id, string $reorderLevel, int $expectedVersion): Inventory
    {
        $inventory = $this->inventories->findActive($id)
            ?? throw new NotFoundHttpException('Inventory item not found');

        $this->em->lock($inventory, LockMode::OPTIMISTIC, $expectedVersion);

        $inventory
            ->setReorderLevel($reorderLevel)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $inventory;
    }

    public function softDelete(Inventory $inventory): void
    {
        if (0 !== Decimal::compare($inventory->getQuantityOnHand() ?? self::ZERO, self::ZERO)
            || 0 !== Decimal::compare($inventory->getQuantityReserved() ?? self::ZERO, self::ZERO)) {
            throw new ConflictHttpException('Inventory still has stock or reservations. Write it off before deleting.');
        }

        $now = new \DateTimeImmutable();
        $this->record(
            $inventory,
            InventoryMovementType::Adjustment,
            self::ZERO,
            self::ZERO,
            'inventory',
            null,
            'Inventory deleted',
        );
        $inventory->setDeletedAt($now)->setUpdatedAt($now);

        $this->em->flush();
    }

    public function apply(
        int $inventoryId,
        InventoryMovementType $type,
        string $quantity,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
    ): AppliedMovement {
        if (InventoryMovementType::InitialStock === $type) {
            throw new UnprocessableEntityHttpException('Initial stock can only be set when the inventory is created.');
        }

        return $this->em->wrapInTransaction(function () use ($inventoryId, $type, $quantity, $referenceType, $referenceId, $reason): AppliedMovement {
            $inventory = $this->em->find(Inventory::class, $inventoryId)
                ?? throw new NotFoundHttpException('Inventory item not found');

            $this->em->refresh($inventory, LockMode::PESSIMISTIC_WRITE);

            if (null !== $inventory->getDeletedAt()) {
                throw new NotFoundHttpException('Inventory item not found');
            }

            if (null !== $referenceType && null !== $referenceId) {
                $existing = $this->movements->findOneBy([
                    'inventory' => $inventory,
                    'type' => $type,
                    'referenceType' => $referenceType,
                    'referenceId' => $referenceId,
                ]);

                if (null !== $existing) {
                    return new AppliedMovement($existing, false);
                }
            }

            [$onHandDelta, $reservedDelta] = $this->deltasFor($type, $quantity);

            return new AppliedMovement(
                $this->record($inventory, $type, $onHandDelta, $reservedDelta, $referenceType, $referenceId, $reason),
                true,
            );
        });
    }

    private function deltasFor(InventoryMovementType $type, string $quantity): array
    {
        $isZero = 0 === Decimal::compare($quantity, self::ZERO);
        $isPositive = Decimal::compare($quantity, self::ZERO) > 0;

        if (InventoryMovementType::Adjustment === $type) {
            if ($isZero) {
                throw new UnprocessableEntityHttpException('Adjustment quantity cannot be zero.');
            }

            return [$quantity, self::ZERO]; // signed
        }

        if (!$isPositive) {
            throw new UnprocessableEntityHttpException('Quantity must be greater than zero.');
        }

        return match ($type) {
            InventoryMovementType::Receipt,
            InventoryMovementType::PurchaseReceipt,
            InventoryMovementType::Return,
            InventoryMovementType::InitialStock => [$quantity, self::ZERO],
            InventoryMovementType::Sale,
            InventoryMovementType::WriteOff => [bcmul($quantity, '-1', self::SCALE), self::ZERO],
            InventoryMovementType::Reserve => [self::ZERO, $quantity],
            InventoryMovementType::Release => [self::ZERO, bcmul($quantity, '-1', self::SCALE)],
            default => throw new UnprocessableEntityHttpException(sprintf('Unsupported movement type "%s".', $type->value)),
        };
    }

    private function record(
        Inventory $inventory,
        InventoryMovementType $type,
        string $onHandDelta,
        string $reservedDelta,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
    ): InventoryMovement {
        $onHandAfter = bcadd($inventory->getQuantityOnHand() ?? self::ZERO, $onHandDelta, self::SCALE);
        $reservedAfter = bcadd($inventory->getQuantityReserved() ?? self::ZERO, $reservedDelta, self::SCALE);

        if (Decimal::compare($onHandAfter, self::ZERO) < 0) {
            throw new ConflictHttpException('Insufficient stock on hand.');
        }

        if (Decimal::compare($reservedAfter, self::ZERO) < 0) {
            throw new ConflictHttpException('Cannot release more than is reserved.');
        }

        if (Decimal::compare($reservedAfter, $onHandAfter) > 0) {
            throw new ConflictHttpException('Reserved quantity cannot exceed quantity on hand.');
        }

        $now = new \DateTimeImmutable();

        $inventory
            ->setQuantityOnHand($onHandAfter)
            ->setQuantityReserved($reservedAfter)
            ->setUpdatedAt($now);

        $changed = 0 !== Decimal::compare($onHandDelta, self::ZERO) ? $onHandDelta : $reservedDelta;

        $movement = (new InventoryMovement())
            ->setType($type)
            ->setQuantity(ltrim($changed, '-'))
            ->setQuantityDelta($onHandDelta)
            ->setBalanceAfter($onHandAfter)
            ->setReservedDelta($reservedDelta)
            ->setReservedAfter($reservedAfter)
            ->setReferenceType($referenceType)
            ->setReferenceId($referenceId)
            ->setReason($reason)
            ->setCreatedAt($now);

        $inventory->addMovement($movement);
        $this->em->persist($movement);

        return $movement;
    }
}
