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
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ContactApiTest extends WebTestCase
{
    public function testContactCreationUsesTheAuthenticatedAuthenticationServiceUser(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $userIds = [$this->userId(37), $this->userId(81), $this->userId(92), $this->userId(104)];
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
            '/api/v1/contacts',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => 'valid-token'],
            content: '{"name":"Acme Supplies","email":"contact@acme.example","type":"client"}',
        );

        self::assertResponseStatusCodeSame(201);
        $response = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $response['status']);
        self::assertSame($userIds[0], $response['data']['attributes']['createdBy']);
        self::assertSame($userIds[0], $response['data']['attributes']['updatedBy']);
        self::assertSame('Acme Supplies', $response['data']['attributes']['name']);
        $contactId = $response['data']['id'];

        $client->request(
            'PATCH',
            '/api/v1/contacts/'.$contactId,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => 'valid-token'],
            content: '{"name":"Acme Incorporated"}',
        );
        self::assertResponseIsSuccessful();
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $updated['status']);
        self::assertSame($userIds[0], $updated['data']['attributes']['createdBy']);
        self::assertSame($userIds[1], $updated['data']['attributes']['updatedBy']);
        self::assertSame('Acme Incorporated', $updated['data']['attributes']['name']);

        $client->request(
            'POST',
            '/api/v1/contacts/'.$contactId.'/deactivate',
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token'],
        );
        self::assertResponseIsSuccessful();
        $deactivated = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $deactivated['status']);
        self::assertSame($userIds[2], $deactivated['data']['attributes']['updatedBy']);
        self::assertSame('inactive', $deactivated['data']['attributes']['status']);

        $client->request(
            'DELETE',
            '/api/v1/contacts/'.$contactId,
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token'],
        );
        self::assertResponseStatusCodeSame(204);

        $entityManager->clear();
        $auditEntries = $entityManager->getRepository(ContactAuditEntry::class)->findAll();
        $actorsByAction = [];
        foreach ($auditEntries as $entry) {
            $actorsByAction[$entry->getAction()] = $entry->getUserId()->toRfc4122();
        }

        self::assertSame([
            'created' => $userIds[0],
            'updated' => $userIds[1],
            'deactivated' => $userIds[2],
            'deleted' => $userIds[3],
        ], $actorsByAction);
    }

    public function testContactApiRejectsRequestsWithoutAnAuthenticationToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/contacts');

        self::assertResponseStatusCodeSame(401);
    }

    public function testContactApiRejectsTokenRejectedByAuthenticationService(): void
    {
        $client = $this->createClientWithAuthenticationResponse(
            new MockResponse('{"message":"Invalid API token."}', ['http_code' => 401]),
        );
        $client->request(
            'GET',
            '/api/v1/contacts',
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
            '/api/v1/contacts',
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
            '/api/v1/contacts',
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

    private function userId(int $number): string
    {
        return Uuid::v5(Uuid::fromString('6ba7b811-9dad-11d1-80b4-00c04fd430c8'), 'api-test-user-' . $number)->toRfc4122();
    }
}
