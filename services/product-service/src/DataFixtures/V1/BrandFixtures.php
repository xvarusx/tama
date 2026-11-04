<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Brand;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BrandFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ([['acme', 'Acme'], ['northwind', 'Northwind'], ['globex', 'Globex']] as [$slug, $name]) {
            $brand = new Brand($name, $slug);
            $manager->persist($brand);
            $this->addReference('brand-'.$slug, $brand);
        }

        $manager->flush();
    }
}
