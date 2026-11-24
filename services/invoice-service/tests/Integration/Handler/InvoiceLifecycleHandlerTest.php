<?php

declare(strict_types=1);

namespace App\Tests\Integration\Handler;

use App\Dto\InvoiceDraftInput;
use App\Dto\InvoiceLineInput;
use App\Entity\Invoice;
use App\Entity\OutboxEvent;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Exceptions\CreditNoteExceedsBalanceException;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Handler\Invoice\CreateDraftInvoiceHandler;
use App\Handler\Invoice\IssueCreditNoteHandler;
use App\Handler\Invoice\IssueInvoiceHandler;
use App\Handler\Invoice\VoidInvoiceHandler;
use App\Repository\OutboxEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class InvoiceLifecycleHandlerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();

        $em = $this->em();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->mockTax();
    }

    public function testIssueAssignsNumberAndDefaultDueDate(): void
    {
        $invoice = $this->draft();
        $issued = $this->issueHandler()($invoice->getId());

        self::assertSame(InvoiceStatus::ISSUED, $issued->getStatus());
        self::assertSame('INV-' . date('Y') . '-000001', $issued->getNumber());
        self::assertSame(
            $issued->getIssueDate()->modify('+30 days')->format('Y-m-d'),
            $issued->getDueDate()->format('Y-m-d'),
        );
        self::assertSame('invoice.issued', $this->lastOutboxEvent()->getEventType());
    }

    public function testExplicitDueDateIsHonoured(): void
    {
        $invoice = $this->draft();
        $due = new DateTimeImmutable('2026-12-31');

        self::assertSame('2026-12-31', $this->issueHandler()($invoice->getId(), $due)->getDueDate()->format('Y-m-d'));
    }

    public function testIssueRejectsDueDateBeforeToday(): void
    {
        $invoice = $this->draft();

        $this->expectException(InvalidInvoiceTransitionException::class);
        $this->issueHandler()($invoice->getId(), new DateTimeImmutable('-1 day'));
    }

    public function testIssueNonDraftThrows(): void
    {
        $invoice = $this->issued();

        $this->expectException(InvalidInvoiceTransitionException::class);
        $this->issueHandler()($invoice->getId());
    }

    public function testIssuedNumbersAreSequential(): void
    {
        $first = $this->issued();
        $second = $this->issueHandler()($this->draft()->getId());

        self::assertSame('INV-' . date('Y') . '-000001', $first->getNumber());
        self::assertSame('INV-' . date('Y') . '-000002', $second->getNumber());
    }

    public function testVoidDraftAndUnpaidIssued(): void
    {
        $draft = $this->draft();
        self::assertSame(InvoiceStatus::VOIDED, $this->voidHandler()($draft->getId(), 'Wrong')->getStatus());

        $issued = $this->issued();
        self::assertSame(InvoiceStatus::VOIDED, $this->voidHandler()($issued->getId(), 'Cancelled')->getStatus());
    }

    public function testVoidPartiallyPaidIsRejected(): void
    {
        $invoice = $this->issued();
        $invoice->applyPayment(Uuid::v7(), 100, 'EUR');
        $this->em()->flush();

        $this->expectException(InvalidInvoiceTransitionException::class);
        $this->voidHandler()($invoice->getId(), 'Cancelled');
    }

    public function testCreditNoteReducesBalanceAndRecordsEvent(): void
    {
        $invoice = $this->issued();
        $before = $invoice->getBalance();
        $updated = $this->creditHandler()($invoice->getId(), 500, 'Correction');

        self::assertSame($before - 500, $updated->getBalance());
        self::assertSame('invoice.credit_note_issued', $this->lastOutboxEvent()->getEventType());
    }

    public function testCreditNoteAboveRemainingValueIsRejected(): void
    {
        $invoice = $this->issued();

        $this->expectException(CreditNoteExceedsBalanceException::class);
        $this->creditHandler()($invoice->getId(), $invoice->getTotal() + 1, 'Too much');
    }

    private function draft(): Invoice
    {
        $invoice = $this->createHandler()(new InvoiceDraftInput(
            Uuid::v7(),
            InvoiceType::SALES,
            Uuid::v7(),
            'Acme',
            'EUR',
            [new InvoiceLineInput(Uuid::v7(), 'Widget', 2, 1000)],
        ));
        self::assertNotNull($invoice);

        return $invoice;
    }

    private function issued(): Invoice
    {
        $invoice = $this->draft();

        return $this->issueHandler()($invoice->getId());
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /** @return list<OutboxEvent> */
    private function outboxEvents(): array
    {
        return self::getContainer()->get(OutboxEventRepository::class)->findPending();
    }

    private function lastOutboxEvent(): OutboxEvent
    {
        $events = $this->outboxEvents();
        self::assertNotSame([], $events);

        return $events[array_key_last($events)];
    }

    private function createHandler(): CreateDraftInvoiceHandler
    {
        return self::getContainer()->get(CreateDraftInvoiceHandler::class);
    }

    private function issueHandler(): IssueInvoiceHandler
    {
        return self::getContainer()->get(IssueInvoiceHandler::class);
    }

    private function voidHandler(): VoidInvoiceHandler
    {
        return self::getContainer()->get(VoidInvoiceHandler::class);
    }

    private function creditHandler(): IssueCreditNoteHandler
    {
        return self::getContainer()->get(IssueCreditNoteHandler::class);
    }

    private function mockTax(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $lines = array_map(
                static fn(array $line): array => [
                    'taxRateBps' => 2000,
                    'taxAmount' => (int)round($line['quantity'] * $line['unitPrice'] * 0.2),
                ],
                $body['lines'],
            );

            return new MockResponse(json_encode(['lines' => $lines], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        self::getContainer()->set(HttpClientInterface::class, $client);
    }
}
