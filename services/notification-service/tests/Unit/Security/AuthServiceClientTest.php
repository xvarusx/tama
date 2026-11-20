<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Exceptions\AuthServiceUnavailableException;
use App\Security\AuthServiceClient;
use App\Security\Caller;
use App\Security\Recipient;
use App\Utils\DemoUserUuids;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AuthServiceClientTest extends TestCase
{
    public function testAuthenticateReturnsCallerFromMeEndpoint(): void
    {
        $seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'id' => DemoUserUuids::forNumber(7),
                'email' => 'jane@example.com',
                'fullName' => 'Jane Doe',
                'roles' => ['ROLE_USER', 'ROLE_ACCOUNTANT'],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $client = $this->makeClient($http);
        $caller = $client->authenticate('token-123');

        self::assertInstanceOf(Caller::class, $caller);
        self::assertSame(DemoUserUuids::forNumber(7), $caller->id);
        self::assertSame('jane@example.com', $caller->email);
        self::assertSame('Jane Doe', $caller->fullName);
        self::assertTrue($caller->hasRole('ROLE_ACCOUNTANT'));
        self::assertSame('GET', $seen[0]['method']);
        self::assertSame('http://auth.test/api/authenticator/me', $seen[0]['url']);
        self::assertSame('X-AUTH-TOKEN: token-123', $seen[0]['options']['headers'][0]);
    }

    public function testAuthenticateRejectsNonUuidCallerId(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode([
            'id' => 7,
            'email' => 'jane@example.com',
            'fullName' => 'Jane Doe',
            'roles' => ['ROLE_USER'],
        ], JSON_THROW_ON_ERROR), ['http_code' => 200]));

        $this->expectException(AuthServiceUnavailableException::class);

        $this->makeClient($http)->authenticate('token-123');
    }

    public function testAuthenticateReturnsNullOn401(): void
    {
        $http = new MockHttpClient(new MockResponse('{"message":"Invalid credentials."}', ['http_code' => 401]));

        self::assertNull($this->makeClient($http)->authenticate('bad-token'));
    }

    public function testResolveAudienceNormalizesRolePrefix(): void
    {
        $seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen[] = ['url' => $url, 'options' => $options];

            return new MockResponse(json_encode([
                'data' => [
                    ['id' => DemoUserUuids::forNumber(1), 'email' => 'a@example.com', 'fullName' => 'Alice', 'roles' => ['ROLE_ACCOUNTANT']],
                    ['id' => DemoUserUuids::forNumber(2), 'email' => 'b@example.com', 'fullName' => 'Bob', 'roles' => ['ROLE_ACCOUNTANT']],
                ],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $recipients = $this->makeClient($http)->resolveAudience(['role' => 'ACCOUNTANT']);

        self::assertCount(2, $recipients);
        self::assertInstanceOf(Recipient::class, $recipients[0]);
        self::assertSame(DemoUserUuids::forNumber(1), $recipients[0]->id);
        self::assertSame('a@example.com', $recipients[0]->email);
        self::assertStringContainsString('role=ROLE_ACCOUNTANT', $seen[0]['url']);
        self::assertStringContainsString('/api/authenticator/internal/users', $seen[0]['url']);
        self::assertSame('X-INTERNAL-TOKEN: internal-secret', $seen[0]['options']['headers'][0]);
    }

    public function testResolveAudiencePassesUuidStringsAsCommaSeparatedIds(): void
    {
        $seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen[] = $url;

            return new MockResponse('{"data":[]}', ['http_code' => 200]);
        });

        $first = DemoUserUuids::forNumber(1);
        $second = DemoUserUuids::forNumber(3);
        self::assertSame([], $this->makeClient($http)->resolveAudience(['userIds' => [$first, $second]]));
        self::assertStringContainsString('ids=' . rawurlencode($first . ',' . $second), $seen[0]);
    }

    public function testResolveAudienceSkipsRecipientsWithInvalidUuid(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode([
            'data' => [
                ['id' => 42, 'email' => 'legacy@example.com', 'fullName' => 'Legacy', 'roles' => []],
            ],
        ], JSON_THROW_ON_ERROR), ['http_code' => 200]));

        self::assertSame([], $this->makeClient($http)->resolveAudience(['role' => 'ACCOUNTANT']));
    }

    public function testResolveAudienceThrowsUnavailableOnHttpFailure(): void
    {
        $http = new MockHttpClient(new MockResponse('{"message":"down"}', ['http_code' => 503]));

        $this->expectException(AuthServiceUnavailableException::class);

        $this->makeClient($http)->resolveAudience(['userIds' => [DemoUserUuids::forNumber(1), DemoUserUuids::forNumber(2)]]);
    }

    private function makeClient(MockHttpClient $http): AuthServiceClient
    {
        return new AuthServiceClient($http, new ArrayAdapter(), 'http://auth.test', 'internal-secret');
    }
}
