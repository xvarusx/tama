<?php

declare(strict_types=1);

namespace App\Tests\Integration\Handler;

use App\Dto\InvoiceDraftInput;
use App\Dto\InvoiceLineInput;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Exceptions\InvoiceAlreadyExistsForOrderException;
use App\Handler\Invoice\CreateDraftInvoiceHandler;
use App\Repository\InvoiceRepository;
use App\Repository\OutboxEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CreateDraftInvoiceHandlerTest extends KernelTestCase
{
    private ?Uuid $competingOrderId = null;

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

    public function testCreatesDraftWithTaxAndOutboxEvent(): void
    {
        $invoice = $this->handler()($this->draftInput());

        self::assertNotNull($invoice);
        self::assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
        self::assertSame(2400, $invoice->getTotal());
        self::assertCount(1, $this->outbox()->findPending());
        self::assertSame('invoice.created', $this->outbox()->findPending()[0]->getEventType());
    }

    public function testDuplicateActiveOrderThrows(): void
    {
        $input = $this->draftInput();
        $this->handler()($input);

        $this->expectException(InvoiceAlreadyExistsForOrderException::class);
        $this->handler()($input);
    }

    public function testDuplicateActiveOrderCanBeIgnored(): void
    {
        $input = $this->draftInput();
        $first = $this->handler()($input);
        self::assertNull($this->handler()($input, ignoreExisting: true));
        self::assertNotNull($first);
        self::assertCount(1, $this->repo()->search(1, 10)[0]);
        self::assertCount(1, $this->outbox()->findPending());
    }

    public function testVoidedOrderCanBeInvoicedAgain(): void
    {
        $input = $this->draftInput();
        $first = $this->handler()($input);
        self::assertNotNull($first);
        $first->void('Wrong');
        $this->em()->flush();

        $second = $this->handler()($input);
        self::assertNotNull($second);
        self::assertNotSame($first->getId()->toRfc4122(), $second->getId()->toRfc4122());
        self::assertCount(2, $this->repo()->search(1, 10)[0]);
    }

    public function testUniqueRaceWhileIgnoringLeavesEntityManagerUsable(): void
    {
        $input = $this->draftInput();
        $this->competingOrderId = $input->orderId;

        self::assertNull($this->handler()($input, ignoreExisting: true));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertTrue($em->isOpen());
        self::assertNotNull($this->handler()($this->draftInput()));
    }

    public function testEmptyLinesAreRejected(): void
    {
        $input = new InvoiceDraftInput(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR', []);

        $this->expectException(InvalidArgumentException::class);
        $this->handler()($input);
    }

    private function handler(): CreateDraftInvoiceHandler
    {
        return self::getContainer()->get(CreateDraftInvoiceHandler::class);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function repo(): InvoiceRepository
    {
        return self::getContainer()->get(InvoiceRepository::class);
    }

    private function outbox(): OutboxEventRepository
    {
        return self::getContainer()->get(OutboxEventRepository::class);
    }

    private function draftInput(): InvoiceDraftInput
    {
        return new InvoiceDraftInput(
            Uuid::v7(),
            InvoiceType::SALES,
            Uuid::v7(),
            'Acme',
            'EUR',
            [new InvoiceLineInput(Uuid::v7(), 'Widget', 2, 1000)],
        );
    }

    private function mockTax(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            if ($this->competingOrderId !== null) {
                $this->insertCompetingInvoice($this->competingOrderId);
                $this->competingOrderId = null;
            }

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

    private function insertCompetingInvoice(Uuid $orderId): void
    {
        $now = new DateTimeImmutable()->format('Y-m-d H:i:s');
        $this->em()->getConnection()->executeStatement(
            'INSERT INTO invoices (id, type, status, order_id, active_order_key, party_id, party_name, currency, amount_paid, overpayment_flagged, version, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Uuid::v7()->toRfc4122(),
                'sales',
                'draft',
                $orderId->toRfc4122(),
                $orderId->toRfc4122(),
                Uuid::v7()->toRfc4122(),
                'Racer',
                'EUR',
                0,
                0,
                1,
                $now,
                $now,
            ],
        );
    }
}
