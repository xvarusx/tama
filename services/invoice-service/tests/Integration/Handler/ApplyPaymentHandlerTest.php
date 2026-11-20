<?php

declare(strict_types=1);

namespace App\Tests\Integration\Handler;

use App\Dto\InvoiceDraftInput;
use App\Dto\InvoiceLineInput;
use App\Entity\Invoice;
use App\Entity\OutboxEvent;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\PaymentResult;
use App\Exceptions\CurrencyMismatchException;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Exceptions\PaymentIdConflictException;
use App\Handler\Invoice\ApplyPaymentHandler;
use App\Handler\Invoice\CreateDraftInvoiceHandler;
use App\Handler\Invoice\IssueInvoiceHandler;
use App\Repository\InvoiceRepository;
use App\Repository\OutboxEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApplyPaymentHandlerTest extends KernelTestCase
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

    public function testAppliesPaymentAndEmitsPaidOnFullAmount(): void
    {
        $invoice = $this->issued();
        $result = $this->handler()($invoice->getId(), Uuid::v7(), $invoice->getBalance(), 'EUR');

        self::assertSame(PaymentResult::APPLIED, $result);
        self::assertSame(InvoiceStatus::PAID, $this->repo()->findByUuid($invoice->getId())->getStatus());
        self::assertSame('invoice.paid', $this->lastOutboxEvent()->getEventType());
    }

    public function testPartialPaymentMarksPartiallyPaid(): void
    {
        $invoice = $this->issued();
        $result = $this->handler()($invoice->getId(), Uuid::v7(), 1000, 'EUR');

        self::assertSame(PaymentResult::APPLIED, $result);
        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $this->repo()->findByUuid($invoice->getId())->getStatus());
    }

    public function testDuplicatePaymentIsIdempotent(): void
    {
        $invoice = $this->issued();
        $paymentId = Uuid::v7();
        $this->handler()($invoice->getId(), $paymentId, 500, 'EUR');
        $result = $this->handler()($invoice->getId(), $paymentId, 500, 'EUR');

        self::assertSame(PaymentResult::DUPLICATE, $result);
        self::assertSame(500, $this->repo()->findByUuid($invoice->getId())->getAmountPaid());
    }

    public function testPaymentIdReusedOnAnotherInvoiceThrows(): void
    {
        $first = $this->issued();
        $second = $this->issued();
        $paymentId = Uuid::v7();
        $this->handler()($first->getId(), $paymentId, 100, 'EUR');

        $this->expectException(PaymentIdConflictException::class);
        $this->handler()($second->getId(), $paymentId, 100, 'EUR');
    }

    public function testOverpaymentIsPersistedAsFlag(): void
    {
        $invoice = $this->issued();
        $result = $this->handler()($invoice->getId(), Uuid::v7(), $invoice->getBalance() + 1, 'EUR');

        self::assertSame(PaymentResult::FLAGGED, $result);
        $reloaded = $this->repo()->findByUuid($invoice->getId());
        self::assertTrue($reloaded->isOverpaymentFlagged());
        self::assertSame(0, $reloaded->getAmountPaid());
        self::assertSame(InvoiceStatus::ISSUED, $reloaded->getStatus());
    }

    public function testDraftAndVoidedAreRejected(): void
    {
        $draft = $this->draft();
        try {
            $this->handler()($draft->getId(), Uuid::v7(), 100, 'EUR');
            self::fail('Expected invalid transition');
        } catch (InvalidInvoiceTransitionException) {
        }

        $voided = $this->issued();
        $voided->void('Cancelled');
        $this->em()->flush();

        $this->expectException(InvalidInvoiceTransitionException::class);
        $this->handler()($voided->getId(), Uuid::v7(), 100, 'EUR');
    }

    public function testCurrencyMismatchIsRejected(): void
    {
        $invoice = $this->issued();

        $this->expectException(CurrencyMismatchException::class);
        $this->handler()($invoice->getId(), Uuid::v7(), 100, 'USD');
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

    private function handler(): ApplyPaymentHandler
    {
        return self::getContainer()->get(ApplyPaymentHandler::class);
    }

    private function createHandler(): CreateDraftInvoiceHandler
    {
        return self::getContainer()->get(CreateDraftInvoiceHandler::class);
    }

    private function issueHandler(): IssueInvoiceHandler
    {
        return self::getContainer()->get(IssueInvoiceHandler::class);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function repo(): InvoiceRepository
    {
        return self::getContainer()->get(InvoiceRepository::class);
    }

    private function lastOutboxEvent(): OutboxEvent
    {
        $events = self::getContainer()->get(OutboxEventRepository::class)->findPending();
        self::assertNotSame([], $events);

        return $events[array_key_last($events)];
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
