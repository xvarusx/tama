<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Support\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ApiInternalUsersControllerTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = $this->resetDatabase();
    }

    public function testRejectsMissingOrWrongToken(): void
    {
        $this->client->request('GET', '/api/authenticator/internal/users', ['role' => 'ACCOUNTANT']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request(
            'GET',
            '/api/authenticator/internal/users',
            ['role' => 'ACCOUNTANT'],
            [],
            ['HTTP_X_INTERNAL_TOKEN' => 'wrong-token'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testFiltersVerifiedUsersByRole(): void
    {
        $accountant = $this->makeVerifiedUser('accountant@example.com', 'Alice Accountant', ['ROLE_ACCOUNTANT']);
        $this->makeVerifiedUser('demo@example.com', 'Demo User', ['ROLE_USER']);
        $this->makeVerifiedUser('unverified@example.com', 'Unverified Accountant', ['ROLE_ACCOUNTANT'], false);

        $this->client->request(
            'GET',
            '/api/authenticator/internal/users',
            ['role' => 'ACCOUNTANT'],
            [],
            $this->internalHeaders(),
        );

        self::assertResponseIsSuccessful();
        $data = $this->responseJson()['data'];

        self::assertCount(1, $data);
        self::assertSame($accountant->getId()->toRfc4122(), $data[0]['id']);
        self::assertSame('accountant@example.com', $data[0]['email']);
        self::assertSame('Alice Accountant', $data[0]['fullName']);
        self::assertContains('ROLE_ACCOUNTANT', $data[0]['roles']);
    }

    public function testFiltersByIds(): void
    {
        $first = $this->makeVerifiedUser('first@example.com', 'First User');
        $second = $this->makeVerifiedUser('second@example.com', 'Second User');
        $this->makeVerifiedUser('third@example.com', 'Third User');

        $this->client->request(
            'GET',
            '/api/authenticator/internal/users',
            ['ids' => sprintf('%s,%s', $first->getId()->toRfc4122(), $second->getId()->toRfc4122())],
            [],
            $this->internalHeaders(),
        );

        self::assertResponseIsSuccessful();
        $data = $this->responseJson()['data'];

        self::assertCount(2, $data);
        self::assertSame([$first->getId()->toRfc4122(), $second->getId()->toRfc4122()], array_column($data, 'id'));
    }

    public function testRejectsNonUuidIds(): void
    {
        $this->makeVerifiedUser('first@example.com', 'First User');

        $this->client->request(
            'GET',
            '/api/authenticator/internal/users',
            ['ids' => '1,2,3'],
            [],
            $this->internalHeaders(),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('valid UUIDs', $this->responseJson()['message']);
    }

    /** @return array<string, string> */
    private function internalHeaders(): array
    {
        return ['HTTP_X_INTERNAL_TOKEN' => $_ENV['AUTH_INTERNAL_TOKEN'] ?? 'test_internal_token'];
    }

    private function makeVerifiedUser(
        string $email,
        string $fullName,
        array $roles = ['ROLE_USER'],
        bool $verified = true,
    ): User {
        $user = $this->createUser($this->em, $email, 'ChangeMe123!', $roles);
        $user->setFullName($fullName);
        $user->setIsVerified($verified);
        $this->em->flush();

        return $user;
    }

    /** @return array<string, mixed> */
    private function responseJson(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
