<?php

namespace App\DataFixtures;

use App\Entity\Inventory;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Util\Decimal;

class InventoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create a product
        $product = new Product();
        $product->setSku('PROD-001');
        $product->setName('Sample Product');
        $product->setStatus('active');
        $manager->persist($product);

        // Create inventory for the product
        $inventory = new Inventory();
        $inventory->setProduct($product);
        $inventory->setQuantityOnHand('100.000');
        $inventory->setQuantityReserved('10.000');
        $inventory->setReorderLevel('20.000');
        $inventory->setCreatedAt(new \DateTimeImmutable());
        $inventory->setUpdatedAt(new \DateTimeImmutable());

        $manager->persist($inventory);

        // Optionally create more inventory entries for other products
        // For demonstration, let's create a second product and inventory
        $product2 = new Product();
        $product2->setSku('PROD-002');
        $product2->setName('Another Product');
        $product2->setStatus('active');
        $manager->persist($product2);

        $inventory2 = new Inventory();
        $inventory2->setProduct($product2);
        $inventory2->setQuantityOnHand('50.000');
        $inventory2->setQuantityReserved('5.000');
        $inventory2->setReorderLevel('10.000');
        $inventory2->setCreatedAt(new \DateTimeImmutable());
        $inventory2->setUpdatedAt(new \DateTimeImmutable());

        $manager->persist($inventory2);

        $manager->flush();
    }
}