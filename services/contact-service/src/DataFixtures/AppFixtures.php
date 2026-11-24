<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Contact;
use App\Entity\ContactAuditEntry;
use App\Enum\ContactType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

final class AppFixtures extends Fixture
{
    /**
     * Deterministic namespace for the authentication-service user ids referenced
     * by the fixtures. Must stay in sync with the authentication-service
     * UserFixtures and the notification-service DemoUserUuids.
     */
    private const USER_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function load(ObjectManager $manager): void
    {
        $user1 = $this->userId(1);
        $user2 = $this->userId(2);

        // Keep the four original demo contacts (with their statuses/actions).
        $client = new Contact('Acme Supplies', $user1);
        $client->setEmail('contact@acme.example');
        $client->setPhone('+1 555 0100');
        $client->setAddress('100 Market Street');
        $client->setTaxNumber('TAX-ACME');

        $supplier = new Contact('Northwind Traders', $user1, ContactType::SUPPLIER);
        $supplier->setEmail('sales@northwind.example');
        $supplier->setTaxNumber('TAX-NORTHWIND');

        $inactive = new Contact('Contoso Parts', $user2);
        $inactive->setTaxNumber('TAX-0003');
        $inactive->deactivate($user2);

        $blacklisted = new Contact('Fabrikam Ltd', $user2);
        $blacklisted->setTaxNumber('TAX-0004');
        $blacklisted->blacklist('Unresolved dispute', $user2);

        $contacts = [$client, $supplier, $inactive, $blacklisted];

        // Add deterministic contacts until there are 30 in total.
        for ($i = 5; $i <= 30; ++$i) {
            $type = 0 === $i % 5 ? ContactType::SUPPLIER : ContactType::CLIENT;
            $contact = new Contact(sprintf('Demo Contact %02d', $i), $this->userId(($i % 3) + 1), $type);
            $contact->setEmail(sprintf('contact%02d@example.test', $i));
            $contact->setPhone(sprintf('+1 555 %04d', 1000 + $i));
            $contact->setAddress(sprintf('%d Demo Avenue', $i));
            $contact->setTaxNumber(sprintf('TAX-%04d', $i));
            $contacts[] = $contact;
        }

        foreach ($contacts as $contact) {
            $manager->persist($contact);
        }

        // Flush to persist the generated contact UUIDs before creating audit entries.
        $manager->flush();

        $audits = [
            new ContactAuditEntry($client->getId(), $user1, 'created'),
            new ContactAuditEntry($supplier->getId(), $user1, 'created'),
            new ContactAuditEntry($inactive->getId(), $user2, 'created'),
            new ContactAuditEntry($blacklisted->getId(), $user2, 'created'),
            new ContactAuditEntry($inactive->getId(), $user2, 'deactivated'),
            new ContactAuditEntry($blacklisted->getId(), $user2, 'blacklisted'),
        ];

        // 24 more audit entries referencing generated contacts (array indices 4..27).
        for ($i = 4; $i < 28; ++$i) {
            $audits[] = new ContactAuditEntry($contacts[$i]->getId(), $this->userId(($i % 3) + 1), 'created');
        }

        foreach ($audits as $audit) {
            $manager->persist($audit);
        }

        $manager->flush();
    }

    private function userId(int $number): Uuid
    {
        return Uuid::v5(Uuid::fromString(self::USER_NAMESPACE), 'erp-demo-user-' . $number);
    }
}
