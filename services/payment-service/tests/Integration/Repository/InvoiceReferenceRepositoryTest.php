<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\InvoiceReference;
use App\Repository\InvoiceReferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InvoiceReferenceRepositoryTest extends KernelTestCase
{
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()
            ->get('doctrine')
            ->getManager();

        // Create schema for tests
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    public function test_save_and_find_by_id(): void
    {
        $invoiceRef = new InvoiceReference(
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'INV-TEST-001',
            'STANDARD',
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            100000,
            100000,
            'ISSUED'
        );

        $this->entityManager->persist($invoiceRef);
        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(InvoiceReference::class);
        $found = $repository->find($invoiceRef->getId());

        $this->assertNotNull($found);
        $this->assertSame('INV-TEST-001', $found->getNumber());
        $this->assertSame(100000, $found->getTotal());
    }

    public function test_find_by_number(): void
    {
        $invoiceRef = new InvoiceReference(
            'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'INV-TEST-002',
            'STANDARD',
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'EUR',
            200000,
            200000,
            'PAID'
        );

        $this->entityManager->persist($invoiceRef);
        $this->entityManager->flush();

        $repository = $this->entityManager->getRepository(InvoiceReference::class);
        $found = $repository->findOneBy(['number' => 'INV-TEST-002']);

        $this->assertNotNull($found);
        $this->assertSame('INV-TEST-002', $found->getNumber());
        $this->assertSame('EUR', $found->getCurrency());
    }

    public function test_find_by_status(): void
    {
        $repo = $this->entityManager->getRepository(InvoiceReference::class);

        // Create an invoice with PAID status
        $paidInvoice = new InvoiceReference(
            'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'INV-TEST-003',
            'STANDARD',
            '22222222-2222-2222-2222-222222222222',
            'Another Corp',
            'USD',
            300000,
            0,
            'PAID'
        );

        $this->entityManager->persist($paidInvoice);
        $this->entityManager->flush();

        $paidInvoices = $repo->findBy(['status' => 'PAID']);
        $this->assertNotEmpty($paidInvoices);
        $this->assertSame('INV-TEST-003', $paidInvoices[0]->getNumber());
    }

    public function test_find_by_party_id(): void
    {
        $repo = $this->entityManager->getRepository(InvoiceReference::class);

        // Create two invoice references with the same party ID
        $invoiceRef1 = new InvoiceReference(
            'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'INV-TEST-004',
            'STANDARD',
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            400000,
            400000,
            'ISSUED'
        );

        $invoiceRef2 = new InvoiceReference(
            'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
            'INV-TEST-005',
            'STANDARD',
            '11111111-1111-1111-1111-111111111111',
            'Test Corp',
            'USD',
            500000,
            500000,
            'ISSUED'
        );

        $this->entityManager->persist($invoiceRef1);
        $this->entityManager->persist($invoiceRef2);
        $this->entityManager->flush();

        $byparty = $repo->findBy(['partyId' => '11111111-1111-1111-1111-111111111111']);
        $this->assertGreaterThanOrEqual(2, count($byparty));
    }

    public function test_count_by_status(): void
    {
        $repo = $this->entityManager->getRepository(InvoiceReference::class);

        $count = $repo->count(['status' => 'ISSUED']);
        $this->assertIsInt($count);
    }
}