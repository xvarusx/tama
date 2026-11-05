<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Unit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UnitFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ([['pcs', false], ['kg', true], ['box', false]] as [$code, $allowDecimal]) {
            $unit = new Unit($code, $allowDecimal);
            $manager->persist($unit);
            $this->addReference('unit-' . $code, $unit);
        }

        $manager->flush();
    }
}
