<?php

namespace App\DataFixtures;

use App\Entity\Category;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $now = new DateTimeImmutable();
        $category = new Category();
        $category->setName('screens');
        $category->setCreatedAt($now);
        $category->setUpdatedAt($now);
        $manager->persist($category);
        $manager->flush();

        $category->setName('keyboards');
        $category->setCreatedAt($now);
        $category->setUpdatedAt($now);
        $manager->persist($category);
        $manager->flush();
    }
}
