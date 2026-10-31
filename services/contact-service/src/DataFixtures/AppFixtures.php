<?php

namespace App\DataFixtures;

use App\Entity\Contact;
use App\Entity\ContactAuditEntry;
use App\Enum\ContactType;
use App\Enum\TrustLevel;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $client = new Contact('Acme Supplies', 1);
        $client->setEmail('contact@acme.example');
        $client->setPhone('+1 555 0100');
        $client->setAddress('100 Market Street');
        $client->setTaxNumber('TAX-ACME');
        $client->changeTrustLevel(TrustLevel::TRUSTED, 1);

        $supplier = new Contact('Northwind Traders', 1, ContactType::SUPPLIER);
        $supplier->setEmail('sales@northwind.example');
        $supplier->setTaxNumber('TAX-NORTHWIND');

        $inactive = new Contact('Contoso Parts', 2);
        $inactive->deactivate(2);

        $blacklisted = new Contact('Fabrikam Ltd', 2);
        $blacklisted->blacklist('Repeated late payments', 2);

        foreach ([
            [$client, 1, 'created'],
            [$client, 1, 'trust_level_changed'],
            [$supplier, 1, 'created'],
            [$inactive, 2, 'created'],
            [$inactive, 2, 'deactivated'],
            [$blacklisted, 2, 'created'],
            [$blacklisted, 2, 'blacklisted'],
        ] as [$contact, $userId, $action]) {
            $manager->persist($contact);
            $manager->persist(new ContactAuditEntry($contact->getId(), $userId, $action));
        }

        $manager->flush();
    }
}
