<?php

namespace App\Tests\Application\Api;

use App\Entity\User;
use App\Tests\Support\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationApplicationTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->resetDatabase();
    }

    public function testRegistrationSendsAWorkingVerificationEmail(): void
    {
        $this->client->jsonRequest('POST', '/api/authenticator/user_register', [
            'email' => ' New.User@example.com ',
            'password' => 'StrongPassword123',
            'fullName' => 'New User',
            'roles' => ['ROLE_ADMIN'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->responseJson();
        self::assertSame('new.user@example.com', $body['email']);

        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'new.user@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNotSame('StrongPassword123', $user->getPassword());

        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $message);
        self::assertEmailAddressContains($message, 'from', 'contact@oussemaharrbi.tn');
        self::assertEmailAddressContains($message, 'to', 'new.user@example.com');
        self::assertEmailSubjectContains($message, 'Please Confirm your Email');
        self::assertEmailHtmlBodyContains($message, 'Please confirm your email address');

        $url = $this->signedUrlFrom($message);

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['message' => 'Your email address has been verified.'],
            $this->responseJson(),
        );

        $this->entityManager->clear();
        $verifiedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $verifiedUser);
        self::assertTrue($verifiedUser->isVerified());
    }

    public function testVerificationLinkDoesNotRequireAnApiToken(): void
    {
        $this->register('person@example.com');

        $url = $this->signedUrlFrom(self::getMailerMessage());

        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testTamperedVerificationLinkIsRejectedAndLeavesTheUserUnverified(): void
    {
        $this->register('person@example.com');
        $user = $this->userByEmail('person@example.com');

        $url = preg_replace('#(signature=)[^&]+#', '$1tampered', $this->signedUrlFrom(self::getMailerMessage()));

        $this->client->request('GET', (string) $url);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertStringContainsString('verify_email_error', $this->responseJson()['message']);

        $this->entityManager->clear();
        self::assertFalse($this->userByEmail($user->getEmail())->isVerified());
    }

    public function testVerificationEndpointRejectsAMissingUserId(): void
    {
        $this->client->request('GET', '/api/authenticator/verify/email');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringContainsString('verify_email_error', $this->responseJson()['message']);
    }

    public function testVerificationEndpointRejectsAnUnknownUserId(): void
    {
        $this->client->request('GET', '/api/authenticator/verify/email?id=999999');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertStringContainsString('verify_email_error', $this->responseJson()['message']);
    }

    public function testRegistrationRejectsDuplicateEmail(): void
    {
        $this->createUser($this->entityManager, 'demo@example.com');

        $this->client->jsonRequest('POST', '/api/authenticator/user_register', [
            'email' => 'DEMO@example.com',
            'password' => 'StrongPassword123',
            'fullName' => 'Demo User',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertEmailCount(0);
    }

    public function testRegistrationRejectsInvalidPayload(): void
    {
        $this->client->jsonRequest('POST', '/api/authenticator/user_register', [
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertEmailCount(0);
    }

    public function testRegistrationRejectsMalformedJson(): void
    {
        $this->client->request(
            'POST',
            '/api/authenticator/user_register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertEmailCount(0);
    }

    private function register(string $email): void
    {
        $this->client->jsonRequest('POST', '/api/authenticator/user_register', [
            'email' => $email,
            'password' => 'StrongPassword123',
            'fullName' => 'Example Person',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    private function userByEmail(string $email): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function signedUrlFrom(TemplatedEmail $message): string
    {
        $html = (string) $message->getHtmlBody();
        self::assertMatchesRegularExpression('#href="[^"]+/api/authenticator/verify/email\?[^"]+"#', $html);
        preg_match('#href="([^"]+)"#', $html, $matches);

        return html_entity_decode($matches[1]);
    }

    private function responseJson(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
