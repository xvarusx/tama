<?php

namespace App\DataFixtures;

use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i <= 100; ++$i) {
            $client = new Client();

            $client->setName("name$i");
            $client->setEmail("tes$i@test.com");
            $client->setPhone("222222$i");
            $client->setAddress("address$i");
            $client->setCreatedAt(new \DateTimeImmutable());

            $manager->persist($client);
        }

        $manager->flush();
    }
}
