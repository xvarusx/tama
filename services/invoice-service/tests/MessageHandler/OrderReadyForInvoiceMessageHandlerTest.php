<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Invoice;
use App\Exceptions\TaxServiceUnavailableException;
use App\Message\OrderReadyForInvoiceMessage;
use App\MessageHandler\OrderReadyForInvoiceMessageHandler;
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

final class OrderReadyForInvoiceMessageHandlerTest extends KernelTestCase
{
    private bool $taxFails = false;

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

    public function testMessageCreatesDraftAndOutboxEvent(): void
    {
        $invoice = $this->handler()($this->message());

        self::assertNotNull($invoice);
        self::assertCount(1, $this->repo()->search(1, 10)[0]);
        self::assertCount(1, $this->outbox()->findPending());
        self::assertSame('invoice.created', $this->outbox()->findPending()[0]->getEventType());
    }

    public function testDuplicateEventIsIgnored(): void
    {
        $message = $this->message();
        $this->handler()($message);
        self::assertNull($this->handler()($message));

        self::assertSame(1, $this->repo()->search(1, 10)[1]);
        self::assertCount(1, $this->outbox()->findPending());
    }

    public function testVoidedOrderEventCreatesReplacement(): void
    {
        $message = $this->message();
        $first = $this->handler()($message);
        self::assertInstanceOf(Invoice::class, $first);
        $first->void('Wrong');
        $this->em()->flush();

        $second = $this->handler()($message);

        self::assertInstanceOf(Invoice::class, $second);
        self::assertNotSame($first->getId()->toRfc4122(), $second->getId()->toRfc4122());
        self::assertSame(2, $this->repo()->search(1, 10)[1]);
    }

    public function testTaxFailurePropagatesForRetryAndPersistsNothing(): void
    {
        $this->mockTaxFailure();

        try {
            $this->handler()($this->message());
            self::fail('The tax failure must propagate so Messenger retries.');
        } catch (TaxServiceUnavailableException) {
        }

        self::assertSame(0, $this->repo()->search(1, 10)[1]);
        self::assertSame(0, $this->outbox()->countPending());
    }

    public function testUnknownEventTypeIsRejectedInsteadOfAcknowledged(): void
    {
        try {
            $this->handler()($this->message('order.created'));
            self::fail('An unknown event type must fail visibly.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('order.created', $e->getMessage());
        }

        self::assertSame(0, $this->repo()->search(1, 10)[1]);
        self::assertSame(0, $this->outbox()->countPending());
    }

    private function handler(): OrderReadyForInvoiceMessageHandler
    {
        return self::getContainer()->get(OrderReadyForInvoiceMessageHandler::class);
    }

    private function message(string $eventType = 'order.shipped'): OrderReadyForInvoiceMessage
    {
        return new OrderReadyForInvoiceMessage(
            $eventType,
            Uuid::v7()->toRfc4122(),
            'sales',
            Uuid::v7()->toRfc4122(),
            'Acme',
            'EUR',
            [[
                'productId' => Uuid::v7()->toRfc4122(),
                'description' => 'Widget',
                'quantity' => 2,
                'unitPrice' => 1000,
            ]],
            new DateTimeImmutable()->format(\DATE_ATOM),
        );
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

    private function mockTax(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            if ($this->taxFails) {
                return new MockResponse('{"error":"down"}', ['http_code' => 500]);
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

    private function mockTaxFailure(): void
    {
        $this->taxFails = true;
    }
}
