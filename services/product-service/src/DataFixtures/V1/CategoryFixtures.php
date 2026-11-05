<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ([['monitors', 'Monitors', 1], ['keyboards', 'Keyboards', 2], ['accessories', 'Accessories', 3]] as [$slug, $name, $position]) {
            $category = new Category($name, $slug, $position);
            $manager->persist($category);
            $this->addReference('category-' . $slug, $category);
        }

        $manager->flush();
    }
}
