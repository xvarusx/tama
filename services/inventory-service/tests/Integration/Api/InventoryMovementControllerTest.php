<?php

namespace App\Tests\Integration\Api;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Util\Decimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\ItemInterface;

final class InventoryMovementControllerTest extends WebTestCase
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

    private function seedInventory(int $productId, string $onHand = '100.000', string $reserved = '10.000', string $reorder = '20.000', ?\DateTimeImmutable $deletedAt = null): int
    {
        $now = new \DateTimeImmutable();
        $inventory = (new Inventory())
            ->setProductId($productId)
            ->setQuantityOnHand($onHand)
            ->setQuantityReserved($reserved)
            ->setReorderLevel($reorder)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setDeletedAt($deletedAt);
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

    private function createMovement(int $id, array $payload): array
    {
        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", $payload);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->body();
    }

    public function testCreateMovementUpdatesBalancesAndRecordsAuditTrail(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7, '100.000', '10.000', '20.000');

        $this->client->request(
            'POST',
            "/api/inventory/{$id}/movements",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'type' => 'sale',
                'quantity' => 5,
                'referenceType' => 'order',
                'referenceId' => 'ORD-1',
                'reason' => 'Customer order',
            ]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->body();
        self::assertSame(7, $body['productId']);
        self::assertSame('sale', $body['type']);
        self::assertSame('5.000', $body['quantity']);
        self::assertSame('order', $body['referenceType']);
        self::assertSame('ORD-1', $body['referenceId']);
        self::assertSame('Customer order', $body['reason']);
        self::assertArrayHasKey('quantityDelta', $body);
        self::assertSame('-5.000', $body['quantityDelta']);
        self::assertSame('95.000', $body['balanceAfter']);
        self::assertSame('0.000', $body['reservedDelta']);
        self::assertSame('10.000', $body['reservedAfter']);

        $em = $this->em();
        $em->clear();
        $inventory = $em->getRepository(Inventory::class)->find($id);
        self::assertSame('95.000', Decimal::format($inventory->getQuantityOnHand()));
        self::assertSame('10.000', Decimal::format($inventory->getQuantityReserved()));
        self::assertCount(1, $em->getRepository(InventoryMovement::class)->findAll());
    }

    public function testListMovementsNewestFirst(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7);

        $this->createMovement($id, ['type' => 'purchase_receipt', 'quantity' => 4]);
        $this->createMovement($id, ['type' => 'sale', 'quantity' => 5]);

        $this->client->request('GET', "/api/inventory/{$id}/movements");

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertCount(2, $body);
        self::assertGreaterThan($body[1]['id'], $body[0]['id']);
        self::assertSame('5.000', $body[0]['quantity']);
    }

    public function testListMovementsIsPaginated(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7);

        $this->createMovement($id, ['type' => 'purchase_receipt', 'quantity' => 1]);
        $this->createMovement($id, ['type' => 'sale', 'quantity' => 1]);
        $this->createMovement($id, ['type' => 'adjustment', 'quantity' => 1]);

        $this->client->request('GET', "/api/inventory/{$id}/movements?page=1&limit=2");

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertCount(2, $this->body());
    }

    public function testMovementsForMissingInventoryReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->client->request('GET', '/api/inventory/999999/movements');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateMovementForMissingInventoryReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory/999999/movements', [
            'type' => 'sale',
            'quantity' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateMovementRejectsQuantityAboveDecimalRange(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7);

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'adjustment',
            'quantity' => '1000000000',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $count = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM inventory_movement');
        self::assertSame(0, (int) $count);
    }

    public function testCreateMovementForMissingInventoryReturns404EvenWithInvalidPayload(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory/999999/movements', [
            'type' => 'bogus',
            'quantity' => 0,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateMovementRejectsZeroQuantity(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7);

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'sale',
            'quantity' => 0,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateMovementRejectsUnknownType(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7);

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'bogus',
            'quantity' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateMovementRequiresTypeAndQuantity(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7);

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateMovementIgnoresPayloadProductId(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7);

        $body = $this->createMovement($id, [
            'productId' => 999,
            'type' => 'adjustment',
            'quantity' => 2,
        ]);

        self::assertSame(7, $body['productId']);
    }

    public function testDeleteInventoryKeepsMovementsReadable(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7, '0.000', '0.000');

        $this->createMovement($id, ['type' => 'purchase_receipt', 'quantity' => 1]);
        $this->createMovement($id, ['type' => 'write_off', 'quantity' => 1]);

        $this->client->request('DELETE', "/api/inventory/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $count = $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM inventory_movement');
        self::assertSame(3, (int) $count);

        $this->client->request('GET', "/api/inventory/{$id}/movements");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->body();
        self::assertCount(3, $body);
        self::assertSame('Inventory deleted', $body[0]['reason']);
        self::assertSame('write_off', $body[1]['type']);
        self::assertSame('purchase_receipt', $body[2]['type']);
    }

    public function testCreateMovementForSoftDeletedInventoryReturns404(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7, '0.000', '0.000', '0.000', new \DateTimeImmutable());

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'sale',
            'quantity' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(0, (int) $this->client->getContainer()->get('doctrine')->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM inventory_movement'));
    }

    public function testCreateMovementForSoftDeletedInventoryReturns404EvenWithInvalidPayload(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7, '0.000', '0.000', '0.000', new \DateTimeImmutable());

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'bogus',
            'quantity' => 0,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateMovementInvalidatesInventoryCache(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->resetDatabase();
        $id = $this->seedInventory(7);

        $cache = $this->client->getContainer()->get('cache.inventory');
        $calls = 0;
        $probe = function (ItemInterface $item) use (&$calls): string {
            $item->tag(['inventory']);
            ++$calls;

            return 'probe';
        };

        $cache->get('movement_probe', $probe);
        $this->createMovement($id, ['type' => 'sale', 'quantity' => 1]);
        $cache->get('movement_probe', $probe);

        self::assertSame(2, $calls);
    }
}
