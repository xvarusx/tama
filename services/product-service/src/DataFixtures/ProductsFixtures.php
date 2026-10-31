<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Random\Engine\Secure;
use Random\Randomizer;

class ProductsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $randomizer = new Randomizer(new Secure());
        // $product = new Product();
        // $manager->persist($product);
        $now = new DateTimeImmutable();
        $category = new Category();
        $category->setName('screen');
        $category->setCreatedAt(new DateTimeImmutable());
        $category->setUpdatedAt(new DateTimeImmutable());
        $manager->persist($category);
        for ($i = 0; $i < 100; $i++) {
            $product = new Product();
            $product->setName("name$i");
            $product->setSku("sku$i");
            $product->setDescription("some descripction");
            $product->setBrand("brand$i");
            $product->setCategory($category);
            $cost = $randomizer->getInt(10, 150);
            $product->setStatus('active');
            $product->setUnit("kg");
            $product->setCostPrice((string) $cost);
            $product->setSellingPrice((string) round($cost * 1.24));
            $product->setCreatedAt($now);
            $product->setUpdatedAt($now);
            $manager->persist($product);
        }

        $manager->flush();
    }
}
