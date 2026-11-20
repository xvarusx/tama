<?php

namespace App\Tests\Controller\Api\V1;

use App\Tests\Support\ProductUuidTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InventoryControllerApplicationTest extends WebTestCase
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

    public function testInventoryLifecycle(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(7),
            'quantityOnHand' => 100,
            'reorderLevel' => 20,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $this->body()['data']['id'];

        $this->client->request('GET', "/api/v1/inventories/{$id}/movements");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $initialMovements = $this->body()['data'];
        self::assertCount(1, $initialMovements);
        self::assertSame('Initial stock', $initialMovements[0]['attributes']['reason']);
        self::assertSame('100.000', $initialMovements[0]['attributes']['quantityDelta']);

        $this->client->request('GET', "/api/v1/inventories/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $shown = $this->body()['data'];
        self::assertSame($this->productUuid(7), $shown['attributes']['productId']);
        self::assertSame('100.000', $shown['attributes']['availableQuantity']);
        self::assertFalse($shown['attributes']['lowStock']);

        $this->jsonRequest('PATCH', "/api/v1/inventories/{$id}", [
            'reorderLevel' => 25,
            'version' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $patched = $this->body()['data'];
        self::assertSame('100.000', $patched['attributes']['quantityOnHand']);
        self::assertSame('100.000', $patched['attributes']['availableQuantity']);
        self::assertSame(2, $patched['attributes']['version']);

        $this->jsonRequest('POST', "/api/v1/inventories/{$id}/movements", [
            'type' => 'out',
            'quantity' => 5,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $movement = $this->body()['data'];
        self::assertSame($this->productUuid(7), $movement['attributes']['productId']);
        self::assertSame('5.000', $movement['attributes']['quantity']);
        self::assertSame('-5.000', $movement['attributes']['quantityDelta']);

        $this->client->request('GET', "/api/v1/inventories/{$id}/movements");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertCount(2, $this->body()['data']);

        $this->client->request('GET', "/api/v1/inventories/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $afterMovement = $this->body()['data'];
        self::assertSame('95.000', $afterMovement['attributes']['quantityOnHand']);
        self::assertSame('0.000', $afterMovement['attributes']['quantityReserved']);

        $this->jsonRequest('POST', "/api/v1/inventories/{$id}/movements", [
            'type' => 'out',
            'quantity' => 95,
            'referenceType' => 'order',
            'referenceId' => 'ORD-2',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $movement = $this->body()['data'];
        self::assertSame($this->productUuid(7), $movement['attributes']['productId']);
        self::assertSame('95.000', $movement['attributes']['quantity']);
        self::assertSame('-95.000', $movement['attributes']['quantityDelta']);

        $this->client->request('DELETE', "/api/v1/inventories/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('GET', "/api/v1/inventories/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', "/api/v1/inventories/{$id}/movements");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $history = $this->body()['data'];
        self::assertCount(4, $history);
        self::assertSame('Inventory deleted', $history[0]['attributes']['reason']);
        self::assertSame('0.000', $history[0]['attributes']['quantityDelta']);
        self::assertSame('0.000', $history[0]['attributes']['quantityAfter']);
    }

    public function testLowStockAndOutOfStockEndpoints(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(70),
            'quantityOnHand' => 5,
            'reorderLevel' => 10,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('GET', '/api/v1/inventories/low-stock');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $lowStock = $this->body()['data'];
        self::assertCount(1, $lowStock);
        self::assertSame($this->productUuid(70), $lowStock[0]['attributes']['productId']);

        $this->jsonRequest('POST', '/api/v1/inventories', [
            'productId' => $this->productUuid(71),
            'quantityOnHand' => 0,
            'reorderLevel' => 5,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('GET', '/api/v1/inventories/out-of-stock');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $outOfStock = $this->body()['data'];
        self::assertCount(1, $outOfStock);
        self::assertSame($this->productUuid(71), $outOfStock[0]['attributes']['productId']);
    }
}
