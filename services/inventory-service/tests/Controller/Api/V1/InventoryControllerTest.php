<?php

namespace App\Tests\Controller\Api\V1;

use App\Entity\Inventory;
use App\Tests\Support\ProductUuidTrait;
use App\Utils\Decimal;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class InventoryControllerTest extends WebTestCase
{
    use ProductUuidTrait;

    private KernelBrowser $client;

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get('doctrine')->getManager();
    }

    private function resetDatabase(): void
    {
        $em = $this->em();
        $em->getConnection()->executeStatement('DELETE FROM inventory_movement');
        $em->getConnection()->executeStatement('DELETE FROM inventory');
        $em->clear();
    }

    private function seedInventory(string $productId, string $onHand = '100.000', string $reserved = '10.000', string $reorder = '20.000'): string
    {
        $now = new DateTimeImmutable();
        $inventory = new Inventory()
            ->setProductId(Uuid::fromString($productId))
            ->setQuantityOnHand($onHand)
            ->setQuantityReserved($reserved)
            ->setReorderLevel($reorder)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $this->em()->persist($inventory);
        $this->em()->flush();

        return $inventory->getId()->toRfc4122();
    }

    private function body(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function jsonRequest(string $method, string $uri, array $payload): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movements(string $id): array
    {
        $this->client->request('GET', "/api/v1/inventories/{$id}/movements?limit=100");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        return $this->body()['data'];
    }

    private function movementCount(): int
    {
        return (int)$this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM inventory_movement');
    }

    public function testIndexIsPaginatedAndOrderedByProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory($this->productUuid(30));
        $this->seedInventory($this->productUuid(10));
        $this->seedInventory($this->productUuid(20));

        $this->client->request('GET', '/api/v1/inventories?page=1&limit=2');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertSame('success', $body['status']);
        self::assertCount(2, $body['data']);

        // Ordering is by the UUID product id, so derive the expected first page.
        $expected = [$this->productUuid(10), $this->productUuid(20), $this->productUuid(30)];
        sort($expected);
        self::assertSame(
            \array_slice($expected, 0, 2),
            array_map(
                static fn(array $item): string => $item['attributes']['productId'],
                $body['data'],
            ),
        );
    }

    public function testShowByIdReturnsInventoryFields(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42));

        $this->client->request('GET', "/api/v1/inventories/{$id}");

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertSame($this->productUuid(42), $body['data']['attributes']['productId']);
        self::assertSame('100.000', $body['data']['attributes']['quantityOnHand']);
        self::assertSame('10.000', $body['data']['attributes']['quantityReserved']);
        self::assertSame('20.000', $body['data']['attributes']['reorderLevel']);
        self::assertSame('90.000', $body['data']['attributes']['availableQuantity']);
        self::assertFalse($body['data']['attributes']['lowStock']);
        self::assertSame(1, $body['data']['attributes']['version']);
        self::assertArrayHasKey('createdAt', $body['data']['attributes']);
        self::assertArrayHasKey('updatedAt', $body['data']['attributes']);
        self::assertArrayHasKey('deletedAt', $body['data']['attributes']);
        self::assertNull($body['data']['attributes']['deletedAt']);
    }

    public function testShowByProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory($this->productUuid(42));

        $this->client->request('GET', '/api/v1/inventories/product/' . $this->productUuid(42));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($this->productUuid(42), $this->body()['data']['attributes']['productId']);
    }

    public function testCreateReturns201AndStoresThreeDecimals(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(42),
            'quantityOnHand' => 100,
            'reorderLevel' => '20',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->body();
        self::assertSame('100.000', $body['data']['attributes']['quantityOnHand']);
        self::assertSame('0.000', $body['data']['attributes']['quantityReserved']);
        self::assertSame('20.000', $body['data']['attributes']['reorderLevel']);
        self::assertSame(1, $body['data']['attributes']['version']);

        $row = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchAssociative('SELECT * FROM inventory WHERE id = ?', [Uuid::fromString($body['data']['id'])], ['uuid']);
        self::assertNotFalse($row);
        self::assertSame('100.000', Decimal::format($row['quantity_on_hand']));
        self::assertSame('0.000', Decimal::format($row['quantity_reserved']));
        self::assertSame('20.000', Decimal::format($row['reorder_level']));
    }

    public function testCreateWithNonZeroBalancesAppendsInitialMovement(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(42),
            'quantityOnHand' => 100,
            'reorderLevel' => '20',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $this->body()['data']['id'];

        $movements = $this->movements($id);
        self::assertCount(1, $movements);
        self::assertSame('adjustment', $movements[0]['attributes']['type']);
        self::assertSame('100.000', $movements[0]['attributes']['quantity']);
        self::assertSame('100.000', $movements[0]['attributes']['quantityDelta']);
        self::assertSame('100.000', $movements[0]['attributes']['quantityAfter']);
        self::assertSame('0.000', $movements[0]['attributes']['reservedDelta']);
        self::assertSame('0.000', $movements[0]['attributes']['reservedAfter']);
        self::assertSame('inventory', $movements[0]['attributes']['referenceType']);
        self::assertSame('Initial stock', $movements[0]['attributes']['reason']);
    }

    public function testCreateWithZeroBalancesAppendsNoMovement(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(45),
            'reorderLevel' => '5',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertSame(0, $this->movementCount());
        self::assertSame([], $this->movements($this->body()['data']['id']));
    }

    public function testCreateRestoresSoftDeletedRowAndKeepsHistory(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42), '0.000', '0.000');

        $this->client->request('GET', "/api/v1/inventories/{$id}");
        $createdAt = $this->body()['data']['attributes']['createdAt'];

        $this->jsonRequest('POST', "/api/v1/inventories/{$id}/movements", [
            'type' => 'in',
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', "/api/v1/inventories/{$id}/movements", [
            'type' => 'out',
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('DELETE', "/api/v1/inventories/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(3, $this->movementCount());

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(42),
            'quantityOnHand' => '50',
            'reorderLevel' => '15',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->body();
        self::assertSame($id, $body['data']['id']);
        self::assertSame('50.000', $body['data']['attributes']['quantityOnHand']);
        self::assertSame('0.000', $body['data']['attributes']['quantityReserved']);
        self::assertSame('15.000', $body['data']['attributes']['reorderLevel']);
        self::assertSame($createdAt, $body['data']['attributes']['createdAt']);

        self::assertSame(
            1,
            (int)$this->client->getContainer()->get('doctrine')->getConnection()
                ->fetchOne(
                    'SELECT COUNT(*) FROM inventory WHERE product_id = ?',
                    [Uuid::fromString($this->productUuid(42))],
                    ['uuid'],
                ),
        );

        $movements = $this->movements($id);
        self::assertCount(4, $movements);
        self::assertSame('Initial stock', $movements[0]['attributes']['reason']);
        self::assertSame('Inventory deleted', $movements[1]['attributes']['reason']);
        self::assertSame('out', $movements[2]['attributes']['type']);
        self::assertSame('50.000', $movements[0]['attributes']['quantityDelta']);
        self::assertSame('50.000', $movements[0]['attributes']['quantityAfter']);
        self::assertSame('0.000', $movements[0]['attributes']['reservedDelta']);
        self::assertSame('0.000', $movements[0]['attributes']['reservedAfter']);

        $this->client->request('GET', '/api/v1/inventories/product/' . $this->productUuid(42));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($id, $this->body()['data']['id']);
    }

    public function testCreateAcceptsLargeDecimal(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(43),
            'quantityOnHand' => '999999999.999',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('999999999.999', $this->body()['data']['attributes']['quantityOnHand']);
    }

    public function testCreateRejectsQuantityAboveDecimalRange(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(44),
            'quantityOnHand' => '1000000000',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateDuplicateProductReturns409(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory($this->productUuid(42));

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(42),
            'quantityOnHand' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(
            'Inventory already exists for this product.',
            $this->body()['error'],
        );

        $count = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM inventory');
        self::assertSame(1, (int)$count);
    }

    public function testCreateRequiresProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsInvalidProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => 1,
            'quantityOnHand' => 10,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsNegativeQuantity(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(1),
            'quantityOnHand' => -1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsNonNumericQuantity(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(1),
            'quantityOnHand' => 'abc',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPatchUpdatesReorderSettingsAndBumpsVersion(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42));

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'reorderLevel' => '25',
            'version' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertSame('100.000', $body['data']['attributes']['quantityOnHand']);
        self::assertSame('10.000', $body['data']['attributes']['quantityReserved']);
        self::assertSame('25.000', $body['data']['attributes']['reorderLevel']);
        self::assertSame(2, $body['data']['attributes']['version']);
    }

    public function testPatchDoesNotChangeStockBalances(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42));

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'reorderLevel' => '30',
            'version' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $inventory = $this->body();
        self::assertSame('100.000', $inventory['data']['attributes']['quantityOnHand']);
        self::assertSame('30.000', $inventory['data']['attributes']['reorderLevel']);
        self::assertCount(0, $this->movements($id));
    }

    public function testReorderOnlyUpdateAppendsNoMovement(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42));

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'reorderLevel' => '30',
            'version' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        self::assertSame(0, $this->movementCount());
    }

    public function testPatchKeepsOmittedFields(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42));

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'reorderLevel' => '30',
            'version' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertSame('100.000', $body['data']['attributes']['quantityOnHand']);
        self::assertSame('30.000', $body['data']['attributes']['reorderLevel']);
    }

    public function testUpdateCannotChangeProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42));

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'productId' => $this->productUuid(99),
            'reorderLevel' => '20',
            'version' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $productId = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT product_id FROM inventory WHERE id = ?', [Uuid::fromString($id)], ['uuid']);
        self::assertSame($this->productUuid(42), Uuid::fromString($productId)->toRfc4122());
    }

    public function testUpdateRejectsQuantityAboveDecimalRange(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42));

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'reorderLevel' => '1000000000',
            'version' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $reorderLevel = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT reorder_level FROM inventory WHERE id = ?', [Uuid::fromString($id)], ['uuid']);
        self::assertSame('20.000', Decimal::format($reorderLevel));
    }

    public function testUpdateWithStaleVersionReturns409(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42));

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'reorderLevel' => '25',
            'version' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'version' => 1,
            'reorderLevel' => '5',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $quantity = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT quantity_on_hand FROM inventory WHERE id = ?', [Uuid::fromString($id)], ['uuid']);
        self::assertSame('100.000', Decimal::format($quantity));
        self::assertSame(0, $this->movementCount());
    }

    public function testDeleteSoftDeletesInventoryAndKeepsHistory(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42), '0.000', '0.000');

        $this->jsonRequest('POST', "/api/v1/inventories/{$id}/movements", [
            'type' => 'in',
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', "/api/v1/inventories/{$id}/movements", [
            'type' => 'out',
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('DELETE', "/api/v1/inventories/{$id}");

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());

        $connection = $this->client->getContainer()->get('doctrine')->getConnection();
        $row = $connection->fetchAssociative('SELECT * FROM inventory WHERE id = ?', [Uuid::fromString($id)], ['uuid']);
        self::assertNotFalse($row);
        self::assertNotNull($row['deleted_at']);
        self::assertSame('0.000', Decimal::format($row['quantity_on_hand']));
        self::assertSame('0.000', Decimal::format($row['quantity_reserved']));

        $this->client->request('GET', "/api/v1/inventories/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', '/api/v1/inventories?limit=100');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->body()['data']);

        $this->client->request('GET', '/api/v1/inventories/product/' . $this->productUuid(42));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', '/api/v1/inventories/low-stock');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->body()['data']);

        $this->client->request('GET', '/api/v1/inventories/out-of-stock');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->body()['data']);

        $movements = $this->movements($id);
        self::assertCount(3, $movements);
        self::assertSame('adjustment', $movements[0]['attributes']['type']);
        self::assertSame('0.000', $movements[0]['attributes']['quantity']);
        self::assertSame('0.000', $movements[0]['attributes']['quantityDelta']);
        self::assertSame('0.000', $movements[0]['attributes']['quantityAfter']);
        self::assertSame('0.000', $movements[0]['attributes']['reservedDelta']);
        self::assertSame('0.000', $movements[0]['attributes']['reservedAfter']);
        self::assertSame('Inventory deleted', $movements[0]['attributes']['reason']);
        self::assertSame('out', $movements[1]['attributes']['type']);
        self::assertSame('in', $movements[2]['attributes']['type']);

        $this->client->request('DELETE', "/api/v1/inventories/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", ['reorderLevel' => '1', 'version' => 1]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowMissingReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->client->request('GET', '/api/v1/inventories/' . $this->productUuid(999999));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowByProductMissingReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->client->request('GET', '/api/v1/inventories/product/' . $this->productUuid(999999));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdateMissingReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('PATCH', '/api/v1/inventories/' . $this->productUuid(999999), ['reorderLevel' => '1', 'version' => 1]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteMissingReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->client->request('DELETE', '/api/v1/inventories/' . $this->productUuid(999999));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testLowStockReturnsItemsAtOrBelowReorderLevel(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory($this->productUuid(1), '10.000', '2.000', '10.000');
        $this->seedInventory($this->productUuid(2), '100.000', '10.000', '20.000');

        $this->client->request('GET', '/api/v1/inventories/low-stock');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertCount(1, $body['data']);
        self::assertSame($this->productUuid(1), $body['data'][0]['attributes']['productId']);
    }

    public function testOutOfStockReturnsZeroAvailable(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory($this->productUuid(3), '5.000', '5.000', '0.000');
        $this->seedInventory($this->productUuid(4), '100.000', '10.000', '20.000');

        $this->client->request('GET', '/api/v1/inventories/out-of-stock');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertCount(1, $body['data']);
        self::assertSame($this->productUuid(3), $body['data'][0]['attributes']['productId']);
    }

    public function testCachedReadIsInvalidatedByUpdate(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->resetDatabase();
        $id = $this->seedInventory($this->productUuid(42), '10.000', '0.000', '5.000');

        $this->client->request('GET', '/api/v1/inventories?limit=100');
        $cachedQuantity = $this->body()['data'][0]['attributes']['quantityOnHand'];
        self::assertSame('10.000', $cachedQuantity);

        $em = $this->em();
        $em->getConnection()->executeStatement(
            'UPDATE inventory SET quantity_on_hand = ? WHERE id = ?',
            ['20.000', Uuid::fromString($id)],
            [Types::STRING, 'uuid'],
        );
        $em->clear();

        $this->client->request('GET', '/api/v1/inventories?limit=100');
        self::assertSame($cachedQuantity, $this->body()['data'][0]['attributes']['quantityOnHand']); // served from cache

        $this->client->request(
            'PATCH',
            "/api/v1/inventories/{$id}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reorderLevel' => '25.000', 'version' => 1]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->client->request('GET', '/api/v1/inventories?limit=100');
        self::assertSame('20.000', $this->body()['data'][0]['attributes']['quantityOnHand']);
    }
}
