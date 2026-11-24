<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\OutboxEvent;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Repository\InvoicePaymentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\OutboxEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceRepositoryTest extends KernelTestCase
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

    public function testFindActiveByOrderIdIgnoresVoided(): void
    {
        $orderId = Uuid::v7();
        $invoice = $this->persistInvoice($orderId, 'INV-2026-000001');
        $invoice->void('wrong');
        $this->em()->flush();

        self::assertNull($this->repo()->findActiveByOrderId($orderId));
    }

    public function testFindActiveByOrderIdReturnsTheActiveInvoice(): void
    {
        $orderId = Uuid::v7();
        $invoice = $this->persistInvoice($orderId, 'INV-2026-000001');

        $active = $this->repo()->findActiveByOrderId($orderId);
        self::assertNotNull($active);
        self::assertSame($invoice->getId()->toRfc4122(), $active->getId()->toRfc4122());
    }

    public function testFindByUuidAcceptsStringAndRejectsInvalidInput(): void
    {
        $invoice = $this->persistInvoice(Uuid::v7(), 'INV-2026-000001');
        $reloaded = $this->repo()->findByUuid($invoice->getId()->toRfc4122());
        self::assertNotNull($reloaded);
        self::assertSame($invoice->getId()->toRfc4122(), $reloaded->getId()->toRfc4122());
        self::assertNull($this->repo()->findByUuid('not-a-uuid'));
    }

    public function testSearchFiltersAndPaginates(): void
    {
        $this->persistInvoice(Uuid::v7(), 'INV-2026-000001', InvoiceStatus::ISSUED, InvoiceType::SALES, 'Acme');
        $this->persistInvoice(Uuid::v7(), 'BILL-2026-000002', InvoiceStatus::ISSUED, InvoiceType::PURCHASE, 'Supplier');

        [$items, $total] = $this->repo()->search(1, 10, InvoiceStatus::ISSUED, InvoiceType::PURCHASE);
        self::assertSame(1, $total);
        self::assertSame('Supplier', $items[0]->getPartyName());
    }

    public function testSearchMatchesNumberOrPartyNameCaseInsensitively(): void
    {
        $this->persistInvoice(Uuid::v7(), 'INV-2026-000001', InvoiceStatus::ISSUED, InvoiceType::SALES, 'Acme');
        $this->persistInvoice(Uuid::v7(), 'BILL-2026-000002', InvoiceStatus::ISSUED, InvoiceType::PURCHASE, 'Supplier');

        [$byName, $totalByName] = $this->repo()->search(1, 10, search: 'supp');
        self::assertSame(1, $totalByName);
        self::assertSame('Supplier', $byName[0]->getPartyName());

        [$byNumber, $totalByNumber] = $this->repo()->search(1, 10, search: 'inv-2026');
        self::assertSame(1, $totalByNumber);
        self::assertSame('INV-2026-000001', $byNumber[0]->getNumber());
    }

    public function testSearchSlicesPagesAndReportsTotal(): void
    {
        $this->persistInvoice(Uuid::v7(), 'INV-2026-000001');
        $this->persistInvoice(Uuid::v7(), 'INV-2026-000002');
        $this->persistInvoice(Uuid::v7(), 'INV-2026-000003');

        [$firstPage, $total] = $this->repo()->search(1, 2);
        [$secondPage] = $this->repo()->search(2, 2);

        self::assertSame(3, $total);
        self::assertCount(2, $firstPage);
        self::assertCount(1, $secondPage);
    }

    public function testSearchOverdueFilter(): void
    {
        $overdue = $this->persistInvoice(Uuid::v7(), 'INV-2026-000001');
        $this->backdateDueDate($overdue, '-1 day');
        $this->persistInvoice(Uuid::v7(), 'INV-2026-000002');
        $this->em()->flush();

        [$overdueItems, $overdueTotal] = $this->repo()->search(1, 10, overdue: true);
        [$currentItems, $currentTotal] = $this->repo()->search(1, 10, overdue: false);

        self::assertSame(1, $overdueTotal);
        self::assertSame('INV-2026-000001', $overdueItems[0]->getNumber());
        self::assertSame(1, $currentTotal);
        self::assertSame('INV-2026-000002', $currentItems[0]->getNumber());
    }

    public function testHighestNumberForTypeAndYear(): void
    {
        $this->persistInvoice(Uuid::v7(), 'INV-2026-000001');
        $this->persistInvoice(Uuid::v7(), 'INV-2026-000007');
        $this->persistInvoice(Uuid::v7(), 'BILL-2026-000004');

        self::assertSame('INV-2026-000007', $this->repo()->highestNumberFor(InvoiceType::SALES, 2026));
        self::assertSame('BILL-2026-000004', $this->repo()->highestNumberFor(InvoiceType::PURCHASE, 2026));
        self::assertNull($this->repo()->highestNumberFor(InvoiceType::SALES, 2025));
    }

    public function testPaymentRepositoryFindsByPaymentId(): void
    {
        $invoice = $this->persistInvoice(Uuid::v7(), 'INV-2026-000001');
        $paymentId = Uuid::v7();
        $invoice->applyPayment($paymentId, 100, 'EUR');
        $this->em()->flush();

        $repository = self::getContainer()->get(InvoicePaymentRepository::class);
        self::assertSame($paymentId->toRfc4122(), $repository->findByPaymentId($paymentId)?->getPaymentId()->toRfc4122());
        self::assertNull($repository->findByPaymentId(Uuid::v7()));
    }

    public function testOutboxRepositoryFindsAndCountsPendingRows(): void
    {
        $em = $this->em();
        $em->persist(new OutboxEvent('invoice.created', 1, ['invoiceId' => 'a']));
        $em->persist(new OutboxEvent('invoice.issued', 1, ['invoiceId' => 'b']));
        $em->flush();

        $repository = self::getContainer()->get(OutboxEventRepository::class);
        self::assertSame(2, $repository->countPending());

        $pending = $repository->findPending();
        self::assertCount(2, $pending);
        $pending[0]->markPublished();
        $em->flush();

        self::assertSame(1, $repository->countPending());
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function repo(): InvoiceRepository
    {
        return self::getContainer()->get(InvoiceRepository::class);
    }

    private function persistInvoice(
        Uuid $orderId,
        string $number,
        InvoiceStatus $status = InvoiceStatus::ISSUED,
        InvoiceType $type = InvoiceType::SALES,
        string $partyName = 'Acme',
    ): Invoice {
        $invoice = new Invoice($orderId, $type, Uuid::v7(), $partyName, 'EUR');
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Widget', 1, 1000, 2000, 200));

        if ($status !== InvoiceStatus::DRAFT) {
            $invoice->issue($number, new DateTimeImmutable('+30 days'));
        }

        $em = $this->em();
        $em->persist($invoice);
        $em->flush();

        return $invoice;
    }

    private function backdateDueDate(Invoice $invoice, string $when): void
    {
        $property = new ReflectionProperty(Invoice::class, 'dueDate');
        $property->setValue($invoice, new DateTimeImmutable($when));
    }
}
