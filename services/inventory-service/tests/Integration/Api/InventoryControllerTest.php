<?php

namespace App\Tests\Integration\Api;

use App\Entity\Inventory;
use App\Util\Decimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InventoryControllerTest extends WebTestCase
{
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

    private function seedInventory(int $productId, string $onHand = '100.000', string $reserved = '10.000', string $reorder = '20.000'): int
    {
        $now = new \DateTimeImmutable();
        $inventory = (new Inventory())
            ->setProductId($productId)
            ->setQuantityOnHand($onHand)
            ->setQuantityReserved($reserved)
            ->setReorderLevel($reorder)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $this->em()->persist($inventory);
        $this->em()->flush();

        return $inventory->getId();
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
    private function movements(int $id): array
    {
        $this->client->request('GET', "/api/inventory/{$id}/movements?limit=100");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        return $this->body();
    }

    private function movementCount(): int
    {
        return (int) $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM inventory_movement');
    }

    public function testIndexIsPaginatedAndOrderedByProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory(30);
        $this->seedInventory(10);
        $this->seedInventory(20);

        $this->client->request('GET', '/api/inventory?page=1&limit=2');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertCount(2, $body);
        self::assertSame([10, 20], array_column($body, 'productId'));
    }

    public function testShowByIdReturnsInventoryFields(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42);

        $this->client->request('GET', "/api/inventory/{$id}");

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertSame(42, $body['productId']);
        self::assertSame('100.000', $body['quantityOnHand']);
        self::assertSame('10.000', $body['quantityReserved']);
        self::assertSame('20.000', $body['reorderLevel']);
        self::assertSame('90.000', $body['availableQuantity']);
        self::assertFalse($body['lowStock']);
        self::assertSame(1, $body['version']);
        self::assertArrayHasKey('createdAt', $body);
        self::assertArrayHasKey('updatedAt', $body);
        self::assertArrayNotHasKey('deletedAt', $body);
    }

    public function testShowByProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory(42);

        $this->client->request('GET', '/api/inventory/product/42');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(42, $this->body()['productId']);
    }

    public function testCreateReturns201AndStoresThreeDecimals(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 42,
            'quantityOnHand' => 100,
            'reorderLevel' => '20',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->body();
        self::assertSame('100.000', $body['quantityOnHand']);
        self::assertSame('0.000', $body['quantityReserved']);
        self::assertSame('20.000', $body['reorderLevel']);
        self::assertSame(1, $body['version']);

        $row = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchAssociative('SELECT * FROM inventory WHERE id = ?', [$body['id']]);
        self::assertNotFalse($row);
        self::assertSame('100.000', Decimal::format($row['quantity_on_hand']));
        self::assertSame('0.000', Decimal::format($row['quantity_reserved']));
        self::assertSame('20.000', Decimal::format($row['reorder_level']));
    }

    public function testCreateWithNonZeroBalancesAppendsInitialMovement(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 42,
            'quantityOnHand' => 100,
            'reorderLevel' => '20',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $this->body()['id'];

        $movements = $this->movements($id);
        self::assertCount(1, $movements);
        self::assertSame('initial_stock', $movements[0]['type']);
        self::assertSame('100.000', $movements[0]['quantity']);
        self::assertSame('100.000', $movements[0]['quantityDelta']);
        self::assertSame('100.000', $movements[0]['balanceAfter']);
        self::assertSame('0.000', $movements[0]['reservedDelta']);
        self::assertSame('0.000', $movements[0]['reservedAfter']);
        self::assertSame('inventory', $movements[0]['referenceType']);
        self::assertSame('Initial stock', $movements[0]['reason']);
    }

    public function testCreateWithZeroBalancesAppendsNoMovement(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 45,
            'reorderLevel' => '5',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertSame(0, $this->movementCount());
        self::assertSame([], $this->movements($this->body()['id']));
    }

    public function testCreateRestoresSoftDeletedRowAndKeepsHistory(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42, '0.000', '0.000');

        $this->client->request('GET', "/api/inventory/{$id}");
        $createdAt = $this->body()['createdAt'];

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'purchase_receipt',
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'write_off',
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('DELETE', "/api/inventory/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(3, $this->movementCount());

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 42,
            'quantityOnHand' => '50',
            'reorderLevel' => '15',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->body();
        self::assertSame($id, $body['id']);
        self::assertSame('50.000', $body['quantityOnHand']);
        self::assertSame('0.000', $body['quantityReserved']);
        self::assertSame('15.000', $body['reorderLevel']);
        self::assertSame($createdAt, $body['createdAt']);

        self::assertSame(
            1,
            (int) $this->client->getContainer()->get('doctrine')->getConnection()
                ->fetchOne('SELECT COUNT(*) FROM inventory WHERE product_id = ?', [42]),
        );

        $movements = $this->movements($id);
        self::assertCount(4, $movements);
        self::assertSame('Initial stock', $movements[0]['reason']);
        self::assertSame('Inventory deleted', $movements[1]['reason']);
        self::assertSame('write_off', $movements[2]['type']);
        self::assertSame('50.000', $movements[0]['quantityDelta']);
        self::assertSame('50.000', $movements[0]['balanceAfter']);
        self::assertSame('0.000', $movements[0]['reservedDelta']);
        self::assertSame('0.000', $movements[0]['reservedAfter']);

        $this->client->request('GET', '/api/inventory/product/42');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($id, $this->body()['id']);
    }

    public function testCreateAcceptsLargeDecimal(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 43,
            'quantityOnHand' => '999999999.999',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('999999999.999', $this->body()['quantityOnHand']);
    }

    public function testCreateRejectsQuantityAboveDecimalRange(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 44,
            'quantityOnHand' => '1000000000',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateDuplicateProductReturns409(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory(42);

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 42,
            'quantityOnHand' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame(
            'Inventory already exists for this product.',
            $this->body()['error'],
        );

        $count = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM inventory');
        self::assertSame(1, (int) $count);
    }

    public function testCreateRequiresProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsNegativeQuantity(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 1,
            'quantityOnHand' => -1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateRejectsNonNumericQuantity(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 1,
            'quantityOnHand' => 'abc',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPatchUpdatesReorderSettingsAndBumpsVersion(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
            'reorderLevel' => '25',
            'version' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertSame('100.000', $body['quantityOnHand']);
        self::assertSame('10.000', $body['quantityReserved']);
        self::assertSame('25.000', $body['reorderLevel']);
        self::assertSame(2, $body['version']);
    }

    public function testPatchDoesNotChangeStockBalances(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
            'reorderLevel' => '30',
            'version' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $inventory = $this->body();
        self::assertSame('100.000', $inventory['quantityOnHand']);
        self::assertSame('30.000', $inventory['reorderLevel']);
        self::assertCount(0, $this->movements($id));
    }

    public function testReorderOnlyUpdateAppendsNoMovement(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
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
        $id = $this->seedInventory(42);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
            'reorderLevel' => '30',
            'version' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertSame('100.000', $body['quantityOnHand']);
        self::assertSame('30.000', $body['reorderLevel']);
    }

    public function testUpdateCannotChangeProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
            'productId' => 99,
            'reorderLevel' => '20',
            'version' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $productId = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT product_id FROM inventory WHERE id = ?', [$id]);
        self::assertSame(42, (int) $productId);
    }

    public function testUpdateRejectsQuantityAboveDecimalRange(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
            'reorderLevel' => '1000000000',
            'version' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $reorderLevel = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT reorder_level FROM inventory WHERE id = ?', [$id]);
        self::assertSame('20.000', Decimal::format($reorderLevel));
    }

    public function testUpdateWithStaleVersionReturns409(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
            'reorderLevel' => '25',
            'version' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
            'version' => 1,
            'reorderLevel' => '5',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $quantity = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT quantity_on_hand FROM inventory WHERE id = ?', [$id]);
        self::assertSame('100.000', Decimal::format($quantity));
        self::assertSame(0, $this->movementCount());
    }

    public function testDeleteSoftDeletesInventoryAndKeepsHistory(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(42, '0.000', '0.000');

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'purchase_receipt',
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'write_off',
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('DELETE', "/api/inventory/{$id}");

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());

        $connection = $this->client->getContainer()->get('doctrine')->getConnection();
        $row = $connection->fetchAssociative('SELECT * FROM inventory WHERE id = ?', [$id]);
        self::assertNotFalse($row);
        self::assertNotNull($row['deleted_at']);
        self::assertSame('0.000', Decimal::format($row['quantity_on_hand']));
        self::assertSame('0.000', Decimal::format($row['quantity_reserved']));

        $this->client->request('GET', "/api/inventory/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', '/api/inventory?limit=100');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->body());

        $this->client->request('GET', '/api/inventory/product/42');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', '/api/inventory/low-stock');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->body());

        $this->client->request('GET', '/api/inventory/out-of-stock');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->body());

        $movements = $this->movements($id);
        self::assertCount(3, $movements);
        self::assertSame('adjustment', $movements[0]['type']);
        self::assertSame('0.000', $movements[0]['quantity']);
        self::assertSame('0.000', $movements[0]['quantityDelta']);
        self::assertSame('0.000', $movements[0]['balanceAfter']);
        self::assertSame('0.000', $movements[0]['reservedDelta']);
        self::assertSame('0.000', $movements[0]['reservedAfter']);
        self::assertSame('Inventory deleted', $movements[0]['reason']);
        self::assertSame('write_off', $movements[1]['type']);
        self::assertSame('purchase_receipt', $movements[2]['type']);

        $this->client->request('DELETE', "/api/inventory/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", ['reorderLevel' => '1', 'version' => 1]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowMissingReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->client->request('GET', '/api/inventory/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowByProductMissingReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->client->request('GET', '/api/inventory/product/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdateMissingReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('PATCH', '/api/inventory/999999', ['reorderLevel' => '1', 'version' => 1]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteMissingReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->client->request('DELETE', '/api/inventory/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testLowStockReturnsItemsAtOrBelowReorderLevel(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory(1, '10.000', '2.000', '10.000');
        $this->seedInventory(2, '100.000', '10.000', '20.000');

        $this->client->request('GET', '/api/inventory/low-stock');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertCount(1, $body);
        self::assertSame(1, $body[0]['productId']);
    }

    public function testOutOfStockReturnsZeroAvailable(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->seedInventory(3, '5.000', '5.000', '0.000');
        $this->seedInventory(4, '100.000', '10.000', '20.000');

        $this->client->request('GET', '/api/inventory/out-of-stock');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertCount(1, $body);
        self::assertSame(3, $body[0]['productId']);
    }

    public function testCachedReadIsInvalidatedByUpdate(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->resetDatabase();
        $id = $this->seedInventory(42, '10.000', '0.000', '5.000');

        $this->client->request('GET', '/api/inventory?limit=100');
        self::assertSame('10.000', $this->body()[0]['quantityOnHand']);

        $em = $this->em();
        $em->getConnection()->executeStatement(
            'UPDATE inventory SET quantity_on_hand = ? WHERE id = ?',
            ['20.000', $id],
        );
        $em->clear();

        $this->client->request('GET', '/api/inventory?limit=100');
        self::assertSame('10.000', $this->body()[0]['quantityOnHand']); // served from cache

        $this->client->request(
            'PATCH',
            "/api/inventory/{$id}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['reorderLevel' => '25.000', 'version' => 1]),
        );
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->client->request('GET', '/api/inventory?limit=100');
        self::assertSame('20.000', $this->body()[0]['quantityOnHand']);
    }
}
