<?php

namespace App\Tests\Application\Api;

use App\Entity\Client;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ClientsControllerApplicationTest extends WebTestCase
{
    private KernelBrowser $client;

    public function testClientLifecycle(): void
    {
        $this->client = static::createClient();
        $this->removeClients();

        $this->client->request('POST', '/api/clients', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Lifecycle',
            'email' => 'lifecycle@example.com',
            'created_at' => '2026-09-20T12:00:00+00:00',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $this->client->request('GET', "/api/clients/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('Lifecycle', json_decode($this->client->getResponse()->getContent(), true)['name']);

        $this->client->request('PATCH', "/api/clients/{$id}", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Lifecycle 2.0']));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('Lifecycle 2.0', json_decode($this->client->getResponse()->getContent(), true)['name']);

        $this->client->request('DELETE', "/api/clients/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', "/api/clients/{$id}");
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->removeClients();
    }

    private function removeClients(): void
    {
        $em = $this->client->getContainer()->get('doctrine')->getManager();
        foreach ($em->getRepository(Client::class)->findAll() as $client) {
            $em->remove($client);
        }
        $em->flush();
        $em->clear();
    }
}
