<?php

namespace App\Tests\Integration\Api;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ClientsControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get('doctrine')->getManager();
    }

    private function seedClient(string $name = 'Test Client'): int
    {
        $em = $this->em();
        $client = (new Client())
            ->setName($name)
            ->setEmail('client@example.com')
            ->setPhone('+33601020304')
            ->setAddress('1 Rue de Test')
            ->setCreatedAt(new \DateTimeImmutable('2026-09-20T12:00:00+00:00'));

        $em->persist($client);
        $em->flush();

        return $client->getId();
    }

    private function removeClients(): void
    {
        $em = $this->em();
        foreach ($em->getRepository(Client::class)->findAll() as $client) {
            $em->remove($client);
        }
        $em->flush();
        $em->clear();
    }

    public function testIndexReturnsAllClients(): void
    {
        $this->client = static::createClient();
        $this->removeClients();
        $this->seedClient('Alpha');
        $this->seedClient('Beta');

        $this->client->request('GET', '/api/clients');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertCount(2, $body);

        $this->removeClients();
    }

    public function testShowByIdReturnsClient(): void
    {
        $this->client = static::createClient();
        $this->removeClients();
        $id = $this->seedClient('Gamma');

        $this->client->request('GET', "/api/clients/{$id}");

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Gamma', $body['name']);
        self::assertSame('client@example.com', $body['email']);

        $this->removeClients();
    }

    public function testCreateCreatesClient(): void
    {
        $this->client = static::createClient();
        $this->removeClients();

        $this->client->request('POST', '/api/clients', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'New Client',
            'email' => 'new@example.com',
            'created_at' => '2026-09-20T12:00:00+00:00',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('New Client', $body['name']);
        self::assertNotNull($body['id']);

        $em = $this->em();
        $em->clear();
        self::assertNotNull($em->getRepository(Client::class)->find($body['id']));

        $this->removeClients();
    }

    public function testUpdateReplacesClient(): void
    {
        $this->client = static::createClient();
        $this->removeClients();
        $id = $this->seedClient('Old Name');

        $this->client->request('PUT', "/api/clients/{$id}", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'id' => $id,
            'name' => 'New Name',
            'email' => 'updated@example.com',
            'created_at' => '2026-09-20T12:00:00+00:00',
        ]));

        self::assertResponseIsSuccessful();

        $em = $this->em();
        $em->clear();
        $updated = $em->getRepository(Client::class)->find($id);
        self::assertSame('New Name', $updated->getName());
        self::assertSame('updated@example.com', $updated->getEmail());

        $this->removeClients();
    }

    public function testDeleteRemovesClient(): void
    {
        $this->client = static::createClient();
        $this->removeClients();
        $id = $this->seedClient('To Delete');

        $this->client->request('DELETE', "/api/clients/{$id}");

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $em = $this->em();
        $em->clear();
        self::assertNull($em->getRepository(Client::class)->find($id));
    }

    public function testShowMissingClientReturns404(): void
    {
        $this->client = static::createClient();
        $this->removeClients();

        $this->client->request('GET', '/api/clients/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Client not found', $body['error']);
    }

    public function testUpdateMissingClientReturns404(): void
    {
        $this->client = static::createClient();
        $this->removeClients();

        $this->client->request('PUT', '/api/clients/999999', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Ghost']));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteMissingClientReturns404(): void
    {
        $this->client = static::createClient();
        $this->removeClients();

        $this->client->request('DELETE', '/api/clients/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testNotAllowedMethodReturns405(): void
    {
        $this->client = static::createClient();
        $this->removeClients();
        $id = $this->seedClient('Method Check');

        $this->client->request('POST', "/api/clients/{$id}");

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);

        $this->removeClients();
    }

    public function testPatchKeepsFieldsNotInPayload(): void
    {
        $this->client = static::createClient();
        $this->removeClients();
        $id = $this->seedClient('Original');

        $this->client->request('PATCH', "/api/clients/{$id}", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Renamed']));

        self::assertResponseIsSuccessful();

        $em = $this->em();
        $em->clear();
        $client = $em->getRepository(Client::class)->find($id);
        self::assertSame('Renamed', $client->getName());
        self::assertSame('client@example.com', $client->getEmail());
        self::assertNotNull($client->getUpdatedAt());

        $this->removeClients();
    }
}
