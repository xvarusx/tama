<?php

namespace App\Tests\Application\Api;

use App\Tests\Support\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationApplicationTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->createUser($this->resetDatabase());
    }

    public function testLoginIssuesATokenThatProtectsApiRoutesUntilLogout(): void
    {
        $this->client->jsonRequest('POST', '/api/authenticator/user_login', [
            'email' => 'demo@example.com',
            'password' => 'ChangeMe123!',
        ]);

        self::assertResponseIsSuccessful();
        $loginResponse = $this->responseJson();
        self::assertArrayHasKey('token', $loginResponse);
        self::assertIsInt($loginResponse['user']['id']);
        self::assertSame('demo@example.com', $loginResponse['user']['email']);

        $token = $loginResponse['token'];
        $this->client->request('GET', '/api/authenticator/me', server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        $currentUser = $this->responseJson();
        self::assertSame($loginResponse['user']['id'], $currentUser['id']);
        self::assertSame('demo@example.com', $currentUser['email']);

        $this->client->request('POST', '/api/authenticator/user_logout', server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/authenticator/me', server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginRejectsIncorrectPassword(): void
    {
        $this->client->jsonRequest('POST', '/api/authenticator/user_login', [
            'email' => 'demo@example.com',
            'password' => 'incorrect',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testProtectedRouteRejectsMissingToken(): void
    {
        $this->client->request('GET', '/api/authenticator/me');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseFormatSame('json');
    }

    private function responseJson(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
