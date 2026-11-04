<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductsFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [BrandFixtures::class, UnitFixtures::class, CategoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $catalog = [
            [
                'name' => '27" 4K Monitor',
                'description' => 'A 27-inch 4K display with USB-C power delivery.',
                'brand' => 'brand-acme',
                'unit' => 'unit-pcs',
                'category' => 'category-monitors',
                'variants' => [
                    ['sku' => 'MON-27', 'costPrice' => '180.00', 'sellingPrice' => '249.00', 'barcode' => '1000000000017', 'options' => ['size' => '27"']],
                    ['sku' => 'MON-27-PRO', 'costPrice' => '260.00', 'sellingPrice' => '349.00', 'barcode' => null, 'options' => ['size' => '27"', 'panel' => 'OLED']],
                ],
            ],
            [
                'name' => 'Mechanical Keyboard',
                'description' => 'Hot-swappable mechanical keyboard with PBT keycaps.',
                'brand' => 'brand-northwind',
                'unit' => 'unit-pcs',
                'category' => 'category-keyboards',
                'variants' => [
                    ['sku' => 'KEY-87', 'costPrice' => '40.00', 'sellingPrice' => '69.00', 'barcode' => null, 'options' => ['layout' => 'TKL', 'switch' => 'brown']],
                    ['sku' => 'KEY-104', 'costPrice' => '45.00', 'sellingPrice' => '79.00', 'barcode' => null, 'options' => ['layout' => 'full', 'switch' => 'blue']],
                ],
            ],
            [
                'name' => 'Wireless Mouse',
                'description' => 'Silent wireless mouse with a 12-month battery.',
                'brand' => 'brand-globex',
                'unit' => 'unit-pcs',
                'category' => 'category-accessories',
                'variants' => [
                    ['sku' => 'MOU-01', 'costPrice' => '12.00', 'sellingPrice' => '24.90', 'barcode' => null, 'options' => null],
                ],
            ],
            [
                'name' => 'USB-C Cable',
                'description' => 'Braided 2 m USB-C to USB-C cable rated for 100 W.',
                'brand' => 'brand-acme',
                'unit' => 'unit-pcs',
                'category' => 'category-accessories',
                'variants' => [
                    ['sku' => 'CAB-01', 'costPrice' => '2.50', 'sellingPrice' => '7.90', 'barcode' => null, 'options' => ['length' => '2 m']],
                ],
            ],
            [
                'name' => 'Monitor Stand',
                'description' => 'Height-adjustable aluminium monitor stand.',
                'brand' => 'brand-northwind',
                'unit' => 'unit-box',
                'category' => 'category-accessories',
                'variants' => [
                    ['sku' => 'STD-01', 'costPrice' => '18.00', 'sellingPrice' => '39.00', 'barcode' => null, 'options' => null],
                ],
            ],
            [
                'name' => 'Laptop Sleeve',
                'description' => 'Water-resistant 14-inch laptop sleeve.',
                'brand' => 'brand-globex',
                'unit' => 'unit-pcs',
                'category' => 'category-accessories',
                'variants' => [
                    ['sku' => 'SLE-01', 'costPrice' => '9.00', 'sellingPrice' => '19.90', 'barcode' => null, 'options' => ['size' => '14"']],
                ],
            ],
            [
                'name' => 'Desk Mat',
                'description' => 'Large felt desk mat with a stitched edge.',
                'brand' => 'brand-acme',
                'unit' => 'unit-pcs',
                'category' => 'category-accessories',
                'variants' => [
                    ['sku' => 'MAT-01', 'costPrice' => '6.00', 'sellingPrice' => '14.50', 'barcode' => null, 'options' => ['size' => '90x40 cm']],
                ],
            ],
            [
                'name' => 'Webcam',
                'description' => 'USB webcam with a privacy shutter and dual microphones.',
                'brand' => 'brand-northwind',
                'unit' => 'unit-pcs',
                'category' => 'category-accessories',
                'variants' => [
                    ['sku' => 'CAM-01', 'costPrice' => '15.00', 'sellingPrice' => '29.90', 'barcode' => null, 'options' => ['resolution' => '1080p']],
                    ['sku' => 'CAM-4K', 'costPrice' => '45.00', 'sellingPrice' => '89.90', 'barcode' => null, 'options' => ['resolution' => '4K']],
                ],
            ],
        ];

        foreach ($catalog as $row) {
            $product = new Product(
                $row['name'],
                $this->getReference($row['brand'], Brand::class),
                $this->getReference($row['unit'], Unit::class),
            );
            $product->setDescription($row['description']);
            $product->setCategory($this->getReference($row['category'], Category::class));

            foreach ($row['variants'] as $variant) {
                $product->addProductVariant(new ProductVariant(
                    $variant['sku'],
                    $variant['costPrice'],
                    $variant['sellingPrice'],
                    $variant['barcode'],
                    $variant['options'],
                ));
            }

            $manager->persist($product);
        }

        $manager->flush();
    }
}
