<?php

declare(strict_types=1);

namespace App\Tests\Integration\Handler;

use App\Dto\InvoiceDraftInput;
use App\Dto\InvoiceLineInput;
use App\Dto\UpdateInvoiceInput;
use App\Enum\InvoiceType;
use App\Exceptions\InvoiceNotEditableException;
use App\Handler\Invoice\CreateDraftInvoiceHandler;
use App\Handler\Invoice\UpdateDraftInvoiceHandler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class UpdateDraftInvoiceHandlerTest extends KernelTestCase
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

    public function testReplacesLinesAndRecalculatesTax(): void
    {
        $invoice = $this->createHandler()($this->draftInput());
        self::assertNotNull($invoice);

        $updated = $this->updateHandler()($invoice->getId(), new UpdateInvoiceInput('Renamed', 'EUR', [
            new InvoiceLineInput(Uuid::v7(), 'New line', 3, 1000),
        ]));

        self::assertSame('Renamed', $updated->getPartyName());
        self::assertCount(1, $updated->getLines());
        self::assertSame(3600, $updated->getTotal());
    }

    public function testIssuedInvoiceCannotBeUpdated(): void
    {
        $invoice = $this->createHandler()($this->draftInput());
        self::assertNotNull($invoice);
        $invoice->issue('INV-2026-000001', new DateTimeImmutable('+30 days'));
        $this->em()->flush();

        $this->expectException(InvoiceNotEditableException::class);
        $this->updateHandler()($invoice->getId(), new UpdateInvoiceInput(null, null, [
            new InvoiceLineInput(Uuid::v7(), 'Y', 1, 100),
        ]));
    }

    public function testEmptyReplacementLinesAreRejected(): void
    {
        $invoice = $this->createHandler()($this->draftInput());
        self::assertNotNull($invoice);

        $this->expectException(InvalidArgumentException::class);
        $this->updateHandler()($invoice->getId(), new UpdateInvoiceInput(null, null, []));
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function createHandler(): CreateDraftInvoiceHandler
    {
        return self::getContainer()->get(CreateDraftInvoiceHandler::class);
    }

    private function updateHandler(): UpdateDraftInvoiceHandler
    {
        return self::getContainer()->get(UpdateDraftInvoiceHandler::class);
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
