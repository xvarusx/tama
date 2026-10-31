<?php

namespace App\Tests\Application\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InventoryControllerApplicationTest extends WebTestCase
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

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 7,
            'quantityOnHand' => 100,
            'reorderLevel' => 20,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $this->body()['id'];

        $this->client->request('GET', "/api/inventory/{$id}/movements");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $initialMovements = $this->body();
        self::assertCount(1, $initialMovements);
        self::assertSame('Initial stock', $initialMovements[0]['reason']);
        self::assertSame('100.000', $initialMovements[0]['quantityDelta']);

        $this->client->request('GET', "/api/inventory/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $shown = $this->body();
        self::assertSame(7, $shown['productId']);
        self::assertSame('100.000', $shown['availableQuantity']);
        self::assertFalse($shown['lowStock']);

        $this->jsonRequest('PATCH', "/api/inventory/{$id}", [
            'reorderLevel' => 25,
            'version' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $patched = $this->body();
        self::assertSame('100.000', $patched['quantityOnHand']);
        self::assertSame('100.000', $patched['availableQuantity']);
        self::assertSame(2, $patched['version']);

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'sale',
            'quantity' => 5,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $movement = $this->body();
        self::assertSame(7, $movement['productId']);
        self::assertSame('5.000', $movement['quantity']);
        self::assertSame('-5.000', $movement['quantityDelta']);

        $this->client->request('GET', "/api/inventory/{$id}/movements");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertCount(2, $this->body());

        $this->client->request('GET', "/api/inventory/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $afterMovement = $this->body();
        self::assertSame('95.000', $afterMovement['quantityOnHand']);
        self::assertSame('0.000', $afterMovement['quantityReserved']);

        $this->jsonRequest('POST', "/api/inventory/{$id}/movements", [
            'type' => 'write_off',
            'quantity' => 95,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('DELETE', "/api/inventory/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('GET', "/api/inventory/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', "/api/inventory/{$id}/movements");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $history = $this->body();
        self::assertCount(4, $history);
        self::assertSame('Inventory deleted', $history[0]['reason']);
        self::assertSame('0.000', $history[0]['quantityDelta']);
        self::assertSame('0.000', $history[0]['balanceAfter']);
    }

    public function testLowStockAndOutOfStockEndpoints(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 70,
            'quantityOnHand' => 5,
            'reorderLevel' => 10,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('GET', '/api/inventory/low-stock');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $lowStock = $this->body();
        self::assertCount(1, $lowStock);
        self::assertSame(70, $lowStock[0]['productId']);

        $this->jsonRequest('POST', '/api/inventory', [
            'productId' => 71,
            'quantityOnHand' => 0,
            'reorderLevel' => 5,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('GET', '/api/inventory/out-of-stock');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $outOfStock = $this->body();
        self::assertCount(1, $outOfStock);
        self::assertSame(71, $outOfStock[0]['productId']);
    }
}
