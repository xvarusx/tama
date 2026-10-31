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

        $contact = new Contact('Northwind Traders', 42, ContactType::SUPPLIER);
        $contact->markUpdatedBy(84);
        $entityManager->persist($contact);
        $entityManager->flush();
        $id = $contact->getId();

        $entityManager->clear();
        $reloaded = $entityManager->getRepository(Contact::class)->find($id);

        self::assertInstanceOf(Contact::class, $reloaded);
        self::assertSame(42, $reloaded->getCreatedBy());
        self::assertSame(84, $reloaded->getUpdatedBy());
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

        self::assertCount(4, $entityManager->getRepository(Contact::class)->findAll());
        self::assertCount(7, $entityManager->getRepository(ContactAuditEntry::class)->findAll());
    }
}
