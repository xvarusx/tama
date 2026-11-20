<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class InvoiceControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->client->getContainer()->set(HttpClientInterface::class, $this->taxClient());

        $em = $this->em();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testCreateShowListAndUpdate(): void
    {
        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->payload(), \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertSame('success', $body['status']);
        self::assertSame('draft', $body['data']['attributes']['status']);
        self::assertSame(2400, $body['data']['attributes']['total']);
        self::assertNotNull($this->client->getResponse()->headers->get('Location'));
        $id = $body['data']['id'];

        $this->client->request('GET', '/api/v1/invoices/' . $id);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($id, $this->json()['data']['id']);

        $this->client->request('GET', '/api/v1/invoices?status=draft&type=sales');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->json()['pagination']['totalItems']);

        $this->client->request('PUT', '/api/v1/invoices/' . $id, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'partyName' => 'Renamed',
            'lines' => [['productId' => Uuid::v7()->toRfc4122(), 'description' => 'New', 'quantity' => 1, 'unitPrice' => 500]],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        self::assertSame('Renamed', $this->json()['data']['attributes']['partyName']);
        self::assertNotNull($this->client->getResponse()->headers->get('Content-Location'));
        self::assertNull($this->client->getResponse()->headers->get('Location'));
    }

    public function testDuplicateActiveOrderReturns409(): void
    {
        $payload = $this->payload();
        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(409);
        self::assertSame('INVOICE_ALREADY_EXISTS_FOR_ORDER', $this->json()['error']['code']);
    }

    public function testValidationErrorReturns422(): void
    {
        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: '{"orderId":"not-a-uuid"}');
        self::assertResponseStatusCodeSame(422);
    }

    public function testMissingInvoiceReturns404(): void
    {
        $this->client->request('GET', '/api/v1/invoices/' . Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
    }

    public function testListFiltersByOverdueAndSearch(): void
    {
        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->payload(), \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/v1/invoices?search=acme');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->json()['pagination']['totalItems']);

        $this->client->request('GET', '/api/v1/invoices?overdue=false');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->json()['pagination']['totalItems']);
    }

    public function testIssueVoidAndCreditNote(): void
    {
        $id = $this->createInvoice();
        $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(200);
        $body = $this->json();
        self::assertSame('issued', $body['data']['attributes']['status']);
        self::assertSame('INV-' . date('Y') . '-000001', $body['data']['attributes']['number']);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/credit-notes', server: ['CONTENT_TYPE' => 'application/json'], content: '{"amount":500,"reason":"Correction"}');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(500, $this->json()['data']['attributes']['creditedAmount']);
    }

    public function testVoidIssuedUnpaid(): void
    {
        $id = $this->createIssuedInvoice();
        $this->client->request('POST', '/api/v1/invoices/' . $id . '/void', server: ['CONTENT_TYPE' => 'application/json'], content: '{"reason":"Cancelled"}');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('voided', $this->json()['data']['attributes']['status']);
    }

    public function testPartialAndFullPaymentsPlusOverpaymentFlag(): void
    {
        $id = $this->createIssuedInvoice();
        $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['paymentId' => Uuid::v7()->toRfc4122(), 'amount' => 1000, 'currency' => 'EUR'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        self::assertSame('partially_paid', $this->json()['data']['attributes']['status']);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['paymentId' => Uuid::v7()->toRfc4122(), 'amount' => 99999, 'currency' => 'EUR'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->json()['data']['attributes']['overpaymentFlagged']);
    }

    public function testFullPaymentMarksInvoicePaid(): void
    {
        $id = $this->createIssuedInvoice();
        $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['paymentId' => Uuid::v7()->toRfc4122(), 'amount' => 2400, 'currency' => 'EUR'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);
        self::assertSame('paid', $this->json()['data']['attributes']['status']);
    }

    public function testIssueNonDraftReturns409AndInvalidPayloadReturns422(): void
    {
        $id = $this->createIssuedInvoice();
        $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(409);
        self::assertSame('INVALID_INVOICE_TRANSITION', $this->json()['error']['code']);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/void', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(422);
    }

    public function testLifecycleActionsOnUnknownInvoiceReturn404(): void
    {
        $this->client->request('POST', '/api/v1/invoices/' . Uuid::v7()->toRfc4122() . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('INVOICE_NOT_FOUND', $this->json()['error']['code']);
    }

    public function testPdfDownloadReturnsAValidPdfForNonAsciiContent(): void
    {
        $id = $this->createIssuedInvoice(['partyName' => 'Ünïcode Café Ltd']);

        $this->client->request('GET', '/api/v1/invoices/' . $id . '/pdf');

        self::assertResponseStatusCodeSame(200);
        self::assertStringStartsWith('application/pdf', (string)$this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=', (string)$this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string)$this->client->getResponse()->getContent());
    }

    public function testPdfDownloadForUnknownInvoiceReturns404(): void
    {
        $this->client->request('GET', '/api/v1/invoices/' . Uuid::v7()->toRfc4122() . '/pdf');

        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidQueryParametersReturn400(): void
    {
        $this->client->request('GET', '/api/v1/invoices?page=0');

        self::assertResponseStatusCodeSame(400);
    }

    public function testWhitespaceOnlyFieldsReturn422(): void
    {
        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([...$this->payload(), 'partyName' => '   '], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            ...$this->payload(),
            'lines' => [['productId' => Uuid::v7()->toRfc4122(), 'description' => '   ', 'quantity' => 1, 'unitPrice' => 100]],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);

        $draftId = $this->createInvoice();
        $this->client->request('PUT', '/api/v1/invoices/' . $draftId, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'partyName' => '   ',
            'lines' => [['productId' => Uuid::v7()->toRfc4122(), 'description' => 'Widget', 'quantity' => 1, 'unitPrice' => 100]],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);

        $id = $this->createIssuedInvoice();

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/void', server: ['CONTENT_TYPE' => 'application/json'], content: '{"reason":"   "}');
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/credit-notes', server: ['CONTENT_TYPE' => 'application/json'], content: '{"amount":100,"reason":"   "}');
        self::assertResponseStatusCodeSame(422);
    }

    public function testNonUuidIdReturns404OnEveryIdRoute(): void
    {
        $id = str_repeat('-', 36);
        $line = ['productId' => Uuid::v7()->toRfc4122(), 'description' => 'Widget', 'quantity' => 1, 'unitPrice' => 100];

        $this->client->request('PUT', '/api/v1/invoices/' . $id, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['partyName' => 'Acme', 'lines' => [$line]], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/void', server: ['CONTENT_TYPE' => 'application/json'], content: '{"reason":"Cancelled"}');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/credit-notes', server: ['CONTENT_TYPE' => 'application/json'], content: '{"amount":100,"reason":"Correction"}');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['paymentId' => Uuid::v7()->toRfc4122(), 'amount' => 100, 'currency' => 'EUR'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/v1/invoices/' . $id . '/pdf');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidDueDatesReturn422AndValidDueDateIsUsed(): void
    {
        $id = $this->createInvoice();

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{"dueDate":"2026-13-01"}');
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{"dueDate":"2026-11-31"}');
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{"dueDate":"2026-12-31"}');
        self::assertResponseStatusCodeSame(200);
        self::assertStringStartsWith('2026-12-31', (string)$this->json()['data']['attributes']['dueDate']);
    }

    public function testPaymentCurrencyMismatchReturns409(): void
    {
        $id = $this->createIssuedInvoice();
        $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['paymentId' => Uuid::v7()->toRfc4122(), 'amount' => 100, 'currency' => 'USD'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('CURRENCY_MISMATCH', $this->json()['error']['code']);
    }

    public function testCreditNoteExceedingBalanceReturns409(): void
    {
        $id = $this->createIssuedInvoice();
        $this->client->request('POST', '/api/v1/invoices/' . $id . '/credit-notes', server: ['CONTENT_TYPE' => 'application/json'], content: '{"amount":2401,"reason":"Too much"}');

        self::assertResponseStatusCodeSame(409);
        self::assertSame('CREDIT_NOTE_EXCEEDS_BALANCE', $this->json()['error']['code']);
    }

    public function testUpdateOnIssuedInvoiceReturns409(): void
    {
        $id = $this->createIssuedInvoice();
        $this->client->request('PUT', '/api/v1/invoices/' . $id, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'partyName' => 'Renamed',
            'lines' => [['productId' => Uuid::v7()->toRfc4122(), 'description' => 'New', 'quantity' => 1, 'unitPrice' => 500]],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('INVOICE_NOT_EDITABLE', $this->json()['error']['code']);
    }

    public function testVoidedOrderCanBeInvoicedAgain(): void
    {
        $payload = $this->payload();
        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $firstId = $this->json()['data']['id'];

        $this->client->request('POST', '/api/v1/invoices/' . $firstId . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/v1/invoices/' . $firstId . '/void', server: ['CONTENT_TYPE' => 'application/json'], content: '{"reason":"Wrong order"}');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($firstId, $this->json()['data']['id']);
    }

    public function testDuplicatePaymentOnPaidInvoiceIsANoOp(): void
    {
        $id = $this->createIssuedInvoice();
        $body = json_encode(['paymentId' => Uuid::v7()->toRfc4122(), 'amount' => 2400, 'currency' => 'EUR'], \JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('paid', $this->json()['data']['attributes']['status']);

        $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('paid', $this->json()['data']['attributes']['status']);
        self::assertSame(2400, $this->json()['data']['attributes']['amountPaid']);
    }

    private function createInvoice(array $overrides = []): string
    {
        $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([...$this->payload(), ...$overrides], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        return $this->json()['data']['id'];
    }

    private function createIssuedInvoice(array $overrides = []): string
    {
        $id = $this->createInvoice($overrides);
        $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(200);

        return $id;
    }

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get(EntityManagerInterface::class);
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        return json_decode((string)$this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'orderId' => Uuid::v7()->toRfc4122(),
            'type' => 'sales',
            'partyId' => Uuid::v7()->toRfc4122(),
            'partyName' => 'Acme',
            'currency' => 'EUR',
            'lines' => [[
                'productId' => Uuid::v7()->toRfc4122(),
                'description' => 'Widget',
                'quantity' => 2,
                'unitPrice' => 1000,
            ]],
        ];
    }

    private function taxClient(): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
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
    }
}
