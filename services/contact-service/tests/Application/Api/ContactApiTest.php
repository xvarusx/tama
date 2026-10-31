<?php

declare(strict_types=1);

namespace App\Tests\Application\Api;

use App\Entity\ContactAuditEntry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ContactApiTest extends WebTestCase
{
    public function testContactCreationUsesTheAuthenticatedAuthenticationServiceUser(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $userIds = [37, 81, 92, 104];
        $requestCount = 0;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requestCount, $userIds): MockResponse {
                self::assertSame('GET', $method);
                self::assertSame('http://authentication-service-web/api/authenticator/me', $url);
                self::assertStringContainsString('X-AUTH-TOKEN: valid-token', implode("\r\n", $options['headers']));

                return new MockResponse(
                    json_encode(['id' => $userIds[$requestCount++]], JSON_THROW_ON_ERROR),
                    ['http_code' => 200],
                );
            },
        );
        static::getContainer()->set(HttpClientInterface::class, $httpClient);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $client->request(
            'POST',
            '/api/contact',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => 'valid-token'],
            content: '{"name":"Acme Supplies","email":"contact@acme.example","contactType":"client"}',
        );

        self::assertResponseStatusCodeSame(201);
        $response = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(37, $response['createdBy']);
        self::assertSame(37, $response['updatedBy']);
        self::assertSame('Acme Supplies', $response['name']);

        $client->request(
            'PATCH',
            '/api/contact/'.$response['id'],
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => 'valid-token'],
            content: '{"name":"Acme Incorporated"}',
        );
        self::assertResponseIsSuccessful();
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(37, $updated['createdBy']);
        self::assertSame(81, $updated['updatedBy']);
        self::assertSame('Acme Incorporated', $updated['name']);

        $client->request(
            'POST',
            '/api/contact/'.$response['id'].'/deactivate',
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token'],
        );
        self::assertResponseIsSuccessful();
        $deactivated = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(92, $deactivated['updatedBy']);
        self::assertSame('inactive', $deactivated['status']);

        $client->request(
            'DELETE',
            '/api/contact/'.$response['id'],
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token'],
        );
        self::assertResponseStatusCodeSame(204);

        $entityManager->clear();
        $auditEntries = $entityManager->getRepository(ContactAuditEntry::class)->findAll();
        $actorsByAction = [];
        foreach ($auditEntries as $entry) {
            $actorsByAction[$entry->getAction()] = $entry->getUserId();
        }

        self::assertSame([
            'created' => 37,
            'updated' => 81,
            'deactivated' => 92,
            'deleted' => 104,
        ], $actorsByAction);
    }

    public function testContactApiRejectsRequestsWithoutAnAuthenticationToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/contact');

        self::assertResponseStatusCodeSame(401);
    }

    public function testContactApiRejectsTokenRejectedByAuthenticationService(): void
    {
        $client = $this->createClientWithAuthenticationResponse(
            new MockResponse('{"message":"Invalid API token."}', ['http_code' => 401]),
        );
        $client->request(
            'GET',
            '/api/contact',
            server: ['HTTP_X_AUTH_TOKEN' => 'invalid-token'],
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testContactApiFailsClosedWhenAuthenticationServiceReturnsAnInvalidUser(): void
    {
        $client = $this->createClientWithAuthenticationResponse(
            new MockResponse('{"id":"37"}', ['http_code' => 200]),
        );
        $client->request(
            'GET',
            '/api/contact',
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token'],
        );

        self::assertResponseStatusCodeSame(503);
    }

    public function testContactApiReturnsUnavailableWhenAuthenticationServiceCannotBeReached(): void
    {
        $client = $this->createClientWithAuthenticationResponse(
            new MockResponse('', ['error' => 'Connection refused']),
        );
        $client->request(
            'GET',
            '/api/contact',
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token'],
        );

        self::assertResponseStatusCodeSame(503);
    }

    private function createClientWithAuthenticationResponse(MockResponse $response): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();
        static::getContainer()->set(HttpClientInterface::class, new MockHttpClient($response));

        return $client;
    }
}
