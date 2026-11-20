<?php

namespace App\DataFixtures;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Entity\Product;
use App\Enum\InventoryMovementType;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class InventoryFixtures extends Fixture
{
    private const int PRODUCT_COUNT = 30;

    public function load(ObjectManager $manager): void
    {
        $now = new DateTimeImmutable();
        // Stable namespace keeps the seeded product UUIDs identical across reloads.
        $namespace = Uuid::fromString(Uuid::NAMESPACE_URL);

        // 30 product projections. The local mirror PK equals the cross-service product UUID.
        $products = [];
        $productIds = [];
        for ($number = 1; $number <= self::PRODUCT_COUNT; ++$number) {
            $productId = Uuid::v5($namespace, sprintf('inventory-product-%03d', $number));
            $productIds[] = $productId;

            $product = new Product()
                ->setId($productId)
                ->setSku(sprintf('PROD-%03d', $number))
                ->setName(sprintf('Product %03d', $number))
                ->setStatus('active');
            $products[] = $product;
            $manager->persist($product);
        }
        $manager->flush();

        // One inventory per product (productId is unique in the database).
        $inventories = [];
        foreach ($productIds as $index => $productId) {
            $number = $index + 1;
            $inventory = new Inventory()
                ->setProductId($productId)
                ->setQuantityOnHand(sprintf('%03d.000', 100 + $number))
                ->setQuantityReserved(sprintf('%02d.000', 5 + ($number % 10)))
                ->setReorderLevel('20.000')
                ->setCreatedAt($now)
                ->setUpdatedAt($now);
            $inventories[] = $inventory;
            $manager->persist($inventory);
        }
        $manager->flush();

        // 30 stock movements spread across the inventories.
        $types = [
            InventoryMovementType::IN,
            InventoryMovementType::OUT,
            InventoryMovementType::ADJUSTMENT,
        ];
        for ($i = 1; $i <= self::PRODUCT_COUNT; ++$i) {
            $inventory = $inventories[($i - 1) % self::PRODUCT_COUNT];
            $type = $types[($i - 1) % 3];

            $movement = new InventoryMovement()
                ->setInventory($inventory) // also sets productId
                ->setType($type)
                ->setQuantity(sprintf('%02d.000', $i))
                ->setQuantityAfter($inventory->getQuantityOnHand())
                ->setBalanceAfter($inventory->getQuantityOnHand())
                ->setReferenceType('fixture')
                ->setReferenceId(sprintf('MOV-%03d', $i))
                ->setReason(sprintf('Fixture movement %03d', $i))
                ->setCreatedAt($now);
            $manager->persist($movement);
        }
        $manager->flush();
    }
}
