<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Enum\InvoiceType;
use App\Service\InvoiceNumberGenerator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceNumberGeneratorTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();

        $em = $this->em();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testNumbersAreSequentialPerTypeAndYear(): void
    {
        $this->persistIssued('INV-2026-000001');
        $this->persistIssued('INV-2026-000007');

        $generator = $this->generator();
        self::assertSame('INV-2026-000008', $generator->next(InvoiceType::SALES, new DateTimeImmutable('2026-06-01')));
        self::assertSame('BILL-2026-000001', $generator->next(InvoiceType::PURCHASE, new DateTimeImmutable('2026-06-01')));
        self::assertSame('INV-2027-000001', $generator->next(InvoiceType::SALES, new DateTimeImmutable('2027-01-01')));
    }

    public function testSequenceContinuesPastSixDigits(): void
    {
        $this->persistIssued('INV-2026-999999');
        $this->persistIssued('INV-2026-1000000');

        self::assertSame(
            'INV-2026-1000001',
            $this->generator()->next(InvoiceType::SALES, new DateTimeImmutable('2026-06-01')),
        );
    }

    public function testLockedCallbackReceivesTheNextNumber(): void
    {
        $generator = $this->generator();
        $number = $generator->locked(
            InvoiceType::SALES,
            new DateTimeImmutable('2026-06-01'),
            static fn(string $number): string => $number,
        );

        self::assertSame('INV-2026-000001', $number);
    }

    public function testLockedSequencesWhenCallbackPersists(): void
    {
        $generator = $this->generator();
        $today = new DateTimeImmutable('2026-06-01');

        $first = $generator->locked(InvoiceType::SALES, $today, function (string $number): string {
            $this->persistIssued($number);

            return $number;
        });
        $second = $generator->locked(InvoiceType::SALES, $today, function (string $number): string {
            $this->persistIssued($number);

            return $number;
        });

        self::assertSame('INV-2026-000001', $first);
        self::assertSame('INV-2026-000002', $second);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function generator(): InvoiceNumberGenerator
    {
        return self::getContainer()->get(InvoiceNumberGenerator::class);
    }

    private function persistIssued(string $number): void
    {
        $invoice = new Invoice(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR');
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Widget', 1, 1000, 2000, 200));
        $invoice->issue($number, new DateTimeImmutable('+30 days'));

        $em = $this->em();
        $em->persist($invoice);
        $em->flush();
    }
}
