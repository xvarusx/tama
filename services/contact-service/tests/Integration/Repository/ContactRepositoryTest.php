<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\AppFixtures;
use App\Entity\Contact;
use App\Entity\ContactAuditEntry;
use App\Enum\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ContactRepositoryTest extends KernelTestCase
{
    public function testPersistsUserIdsAndContactType(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $creator = Uuid::v5(Uuid::fromString('6ba7b811-9dad-11d1-80b4-00c04fd430c8'), 'repo-test-user-42');
        $updater = Uuid::v5(Uuid::fromString('6ba7b811-9dad-11d1-80b4-00c04fd430c8'), 'repo-test-user-84');
        $contact = new Contact('Northwind Traders', $creator, ContactType::SUPPLIER);
        $contact->markUpdatedBy($updater);
        $entityManager->persist($contact);
        $entityManager->flush();
        $id = $contact->getId();

        $entityManager->clear();
        $reloaded = $entityManager->getRepository(Contact::class)->find($id);

        self::assertInstanceOf(Contact::class, $reloaded);
        self::assertSame($creator->toRfc4122(), $reloaded->getCreatedBy()?->toRfc4122());
        self::assertSame($updater->toRfc4122(), $reloaded->getUpdatedBy()?->toRfc4122());
        self::assertSame(ContactType::SUPPLIER, $reloaded->getContactType());
    }

    public function testContactFixturesLoadRepresentativeContactStatesAndActors(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        (new AppFixtures())->load($entityManager);

        self::assertCount(30, $entityManager->getRepository(Contact::class)->findAll());
        self::assertCount(30, $entityManager->getRepository(ContactAuditEntry::class)->findAll());
    }
}
