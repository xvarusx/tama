<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Dto\InvoiceDraftInput;
use App\Dto\InvoiceLineInput;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Handler\Invoice\CreateDraftInvoiceHandler;
use App\Handler\Invoice\IssueInvoiceHandler;
use App\Message\PaymentReceivedMessage;
use App\MessageHandler\PaymentReceivedMessageHandler;
use App\Repository\InvoiceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PaymentReceivedMessageHandlerTest extends KernelTestCase
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

    public function testPaymentIsAppliedOnce(): void
    {
        $invoice = $this->issued();
        $message = $this->message($invoice, 1000);

        $this->handler()($message);
        $this->handler()($message);

        self::assertSame(1000, $this->repo()->findByUuid($invoice->getId())->getAmountPaid());
        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $this->repo()->findByUuid($invoice->getId())->getStatus());
    }

    public function testFullAmountMarksInvoicePaid(): void
    {
        $invoice = $this->issued();
        $this->handler()($this->message($invoice, $invoice->getTotal()));

        self::assertSame(InvoiceStatus::PAID, $this->repo()->findByUuid($invoice->getId())->getStatus());
    }

    public function testOverpaymentFlagsInvoice(): void
    {
        $invoice = $this->issued();
        $this->handler()($this->message($invoice, $invoice->getTotal() + 1));

        self::assertTrue($this->repo()->findByUuid($invoice->getId())->isOverpaymentFlagged());
        self::assertSame(0, $this->repo()->findByUuid($invoice->getId())->getAmountPaid());
    }

    private function handler(): PaymentReceivedMessageHandler
    {
        return self::getContainer()->get(PaymentReceivedMessageHandler::class);
    }

    private function message(Invoice $invoice, int $amount): PaymentReceivedMessage
    {
        return new PaymentReceivedMessage(
            $invoice->getId()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            $amount,
            'EUR',
            new DateTimeImmutable()->format(\DATE_ATOM),
        );
    }

    private function issued(): Invoice
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

        return $this->issueHandler()($invoice->getId());
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
