<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderType;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class OrdersControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Keep the same kernel (and therefore the same in-memory SQLite schema) across
        // the two requests issued by tests such as testUpdateOrderLines().
        $this->client->disableReboot();
        $entityManager = $this->em();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get('doctrine')->getManager();
    }

    private function seedOrder(
        string $number,
        OrderType $type,
        string $partyId,
        string $partyName,
        string $currency = 'USD',
        OrderStatus $status = OrderStatus::DRAFT
    ): Order {
        $em = $this->em();

        $order = new Order($number, $type, $partyId, $partyName, $currency);

        // Add a line
        $line = new OrderLine();
        $line->setProductId(new Uuid('00000000-0000-0000-0000-000000000001'));
        $line->setProductName('Test Product');
        $line->setSku('TEST-001');
        $line->setQuantity(10);
        $line->setUnitPrice(1000);
        $order->addLine($line);

        // Set status if not DRAFT
        if ($status !== OrderStatus::DRAFT) {
            match ($status) {
                OrderStatus::SUBMITTED => $order->submit(),
                OrderStatus::RECEIVED => $order->submit(),
                OrderStatus::CANCELED => $order->submit(),
                OrderStatus::SHIPPED => $order->submit(),
                OrderStatus::DELIVERED => $order->submit(),
                OrderStatus::PARTIALLY_RECEIVED => $order->submit(),
                OrderStatus::STOCK_REJECTED => $order->submit(),
                OrderStatus::DRAFT => null,
            };
        }

        $em->persist($order);
        $em->flush();

        return $order;
    }

    public function testListOrders(): void
    {
        $order = $this->seedOrder('PO-001', OrderType::PURCHASE, 'supplier-001', 'Test Supplier');

        $this->client->request('GET', '/api/v1/orders');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertIsArray($response);
        self::assertGreaterThanOrEqual(1, count($response));
    }

    public function testCreateOrder(): void
    {
        $this->client->request('POST', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'number' => 'PO-002',
            'type' => 'purchase',
            'partyId' => 'supplier-002',
            'partyName' => 'Another Supplier',
            'currency' => 'USD',
            'lines' => [
                [
                    'productId' => (string) Uuid::v7(),
                    'productName' => 'Test Product',
                    'sku' => 'TEST-002',
                    'quantity' => 5,
                    'unitPrice' => 2000,
                ],
            ],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertArrayHasKey('id', $response);
        self::assertArrayHasKey('attributes', $response);
        self::assertSame('PO-002', $response['attributes']['number']);
        self::assertSame('purchase', $response['attributes']['type']);
        self::assertSame('draft', $response['attributes']['status']);
    }

    public function testCreateOrderWithInvalidDataReturns422(): void
    {
        $this->client->request('POST', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'number' => '', // blank number
            'type' => 'invalid',
            'partyId' => 'supplier-003',
            'partyName' => 'Test Supplier',
            'currency' => 'USD',
            'lines' => [], // no lines
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testShowOrder(): void
    {
        $order = $this->seedOrder('PO-003', OrderType::PURCHASE, 'supplier-003', 'Test Supplier');

        $this->client->request('GET', '/api/v1/orders/' . $order->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame($order->getId()->toRfc4122(), $response['id']);
        self::assertSame('PO-003', $response['attributes']['number']);
    }

    public function testShowNonExistentOrderReturns404(): void
    {
        $this->client->request('GET', '/api/v1/orders/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdateOrder(): void
    {
        $order = $this->seedOrder('PO-004', OrderType::PURCHASE, 'supplier-004', 'Test Supplier');

        $this->client->request('PATCH', '/api/v1/orders/' . $order->getId()->toRfc4122(), [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'lines' => [
                [
                    'productId' => (string) Uuid::v7(),
                    'productName' => 'Updated Product',
                    'sku' => 'UPD-001',
                    'quantity' => 15,
                    'unitPrice' => 1500,
                ],
            ],
            'notes' => 'Updated notes',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('PO-004', $response['attributes']['number']);
    }

    public function testUpdateOrderLines(): void
    {
        $order = $this->seedOrder('PO-005', OrderType::PURCHASE, 'supplier-005', 'Test Supplier');

        // Submit the order first
        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/submit');

        $this->client->request('PATCH', '/api/v1/orders/' . $order->getId()->toRfc4122(), [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'lines' => [
                [
                    'productId' => (string) Uuid::v7(),
                    'productName' => 'New Product',
                    'sku' => 'NEW-001',
                    'quantity' => 20,
                    'unitPrice' => 2500,
                ],
            ],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testDeleteOrder(): void
    {
        $order = $this->seedOrder('PO-006', OrderType::PURCHASE, 'supplier-006', 'Test Supplier');

        $this->client->request('DELETE', '/api/v1/orders/' . $order->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify order is deleted
        $em = $this->em();
        $em->clear();
        $found = $em->getRepository(Order::class)->find($order->getId());
        self::assertNull($found, 'Order should be deleted but was found');
    }

    public function testSubmitOrder(): void
    {
        $order = $this->seedOrder('PO-007', OrderType::PURCHASE, 'supplier-007', 'Test Supplier');

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/submit');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('submitted', $response['attributes']['status']);
        self::assertNotNull($response['attributes']['submittedAt']);
    }

    public function testSubmitOrderWithNoLinesReturns409(): void
    {
        $em = $this->em();
        $order = new Order('PO-008', OrderType::PURCHASE, 'supplier-008', 'Test Supplier', 'USD');
        $em->persist($order);
        $em->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/submit');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testSubmitAlreadySubmittedOrderReturns409(): void
    {
        $order = $this->seedOrder('PO-009', OrderType::PURCHASE, 'supplier-009', 'Test Supplier');
        $order->submit();
        $this->em()->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/submit');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testReceiveGoods(): void
    {
        $order = $this->seedOrder('PO-010', OrderType::PURCHASE, 'supplier-010', 'Test Supplier');
        $order->submit();
        $this->em()->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/receive', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'quantities' => [1 => ['quantity' => 5]],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('received', $response['attributes']['status']);
    }

    public function testCancelOrder(): void
    {
        $order = $this->seedOrder('PO-011', OrderType::PURCHASE, 'supplier-011', 'Test Supplier');

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/cancel');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('canceled', $response['attributes']['status']);
    }

    public function testCancelAlreadyCanceledOrderReturns409(): void
    {
        $order = $this->seedOrder('PO-012', OrderType::PURCHASE, 'supplier-012', 'Test Supplier');
        $order->cancel();
        $this->em()->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/cancel');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testCancelDraftOrder(): void
    {
        $order = $this->seedOrder('PO-013', OrderType::PURCHASE, 'supplier-013', 'Test Supplier');

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/cancel');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('canceled', $response['attributes']['status']);
    }

    public function testShipOrder(): void
    {
        $order = $this->seedOrder('SO-001', OrderType::SALES, 'customer-001', 'Test Customer');
        $order->submit();
        $order->receive([1 => ['quantity' => 10]]);
        $this->em()->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/ship', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'quantities' => [1 => ['quantity' => 10]],
            'trackingNumber' => 'TRK-12345',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('shipped', $response['attributes']['status']);
    }

    public function testShipPurchaseOrderReturns409(): void
    {
        $order = $this->seedOrder('PO-014', OrderType::PURCHASE, 'supplier-014', 'Test Supplier');
        $order->submit();
        $order->receive([1 => ['quantity' => 10]]);
        $this->em()->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/ship', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'quantities' => [1 => ['quantity' => 10]],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testMarkOrderDelivered(): void
    {
        $order = $this->seedOrder('SO-002', OrderType::SALES, 'customer-002', 'Test Customer');
        $order->submit();
        $order->receive([1 => ['quantity' => 10]]);
        $order->ship([1 => ['quantity' => 10]]);
        $this->em()->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/deliver');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('delivered', $response['attributes']['status']);
    }

    public function testMarkNonShippedOrderAsDeliveredReturns409(): void
    {
        $order = $this->seedOrder('SO-003', OrderType::SALES, 'customer-003', 'Test Customer');
        $this->em()->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/deliver');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testMarkPurchaseOrderAsDeliveredReturns409(): void
    {
        $order = $this->seedOrder('PO-015', OrderType::PURCHASE, 'supplier-015', 'Test Supplier');
        $order->submit();
        $this->em()->flush();

        $this->client->request('POST', '/api/v1/orders/' . $order->getId()->toRfc4122() . '/deliver');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testOrderTotalsCalculated(): void
    {
        $this->client->request('POST', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'number' => 'PO-016',
            'type' => 'sales',
            'partyId' => 'customer-016',
            'partyName' => 'Test Customer',
            'currency' => 'USD',
            'lines' => [
                [
                    'productId' => (string) Uuid::v7(),
                    'productName' => 'Product A',
                    'sku' => 'A-001',
                    'quantity' => 5,
                    'unitPrice' => 1000, // $10.00
                ],
                [
                    'productId' => (string) Uuid::v7(),
                    'productName' => 'Product B',
                    'sku' => 'B-001',
                    'quantity' => 10,
                    'unitPrice' => 500, // $5.00
                ],
            ],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(10000, $response['attributes']['totalAmount']); // (5 * 1000) + (10 * 500) = 10000
    }
}