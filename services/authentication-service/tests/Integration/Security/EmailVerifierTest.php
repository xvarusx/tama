<?php

namespace App\Tests\Integration\Security;

use App\Entity\User;
use App\Security\EmailVerifier;
use App\Tests\Support\DatabaseTestTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\Exception\InvalidSignatureException;

final class EmailVerifierTest extends KernelTestCase
{
    use DatabaseTestTrait;

    public function testTheSignedLinkSentInTheEmailConfirmsTheUser(): void
    {
        self::bootKernel();
        $entityManager = $this->resetDatabase();
        $user = $this->createUser($entityManager, 'person@example.com');

        $this->emailVerifier()->sendEmailConfirmation(
            'api_verify_email',
            $user,
            $this->confirmationEmail('person@example.com'),
        );

        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $message);
        self::assertEmailAddressContains($message, 'to', 'person@example.com');
        self::assertEmailSubjectContains($message, 'Please Confirm your Email');

        $url = $this->signedUrlFrom($message);
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame((string) $user->getId(), $query['id'] ?? null);
        self::assertArrayHasKey('token', $query);
        self::assertArrayHasKey('expires', $query);
        self::assertArrayHasKey('signature', $query);

        $this->emailVerifier()->handleEmailConfirmation(Request::create($url), $user);

        $entityManager->clear();
        $verifiedUser = $entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $verifiedUser);
        self::assertTrue($verifiedUser->isVerified());
    }

    public function testATamperedSignedLinkIsRejectedAndLeavesTheUserUnverified(): void
    {
        self::bootKernel();
        $entityManager = $this->resetDatabase();
        $user = $this->createUser($entityManager, 'person@example.com');

        $this->emailVerifier()->sendEmailConfirmation(
            'api_verify_email',
            $user,
            $this->confirmationEmail('person@example.com'),
        );

        $url = $this->tamperedUrlFrom(self::getMailerMessage());

        try {
            $this->emailVerifier()->handleEmailConfirmation(Request::create($url), $user);
            self::fail('A tampered link must not confirm the address.');
        } catch (InvalidSignatureException) {
            $entityManager->clear();
            $unverifiedUser = $entityManager->getRepository(User::class)->find($user->getId());
            self::assertInstanceOf(User::class, $unverifiedUser);
            self::assertFalse($unverifiedUser->isVerified());
        }
    }

    private function emailVerifier(): EmailVerifier
    {
        return static::getContainer()->get(EmailVerifier::class);
    }

    private function confirmationEmail(string $to): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address('no-replay@oussemaharrbi.tn', 'oh Mail Bot'))
            ->to($to)
            ->subject('Please Confirm your Email')
            ->htmlTemplate('registration/confirmation_email.html.twig');
    }

    private function signedUrlFrom(TemplatedEmail $message): string
    {
        $html = (string) $message->getHtmlBody();
        self::assertMatchesRegularExpression('#href="[^"]+/api/authenticator/verify/email\?[^"]+"#', $html);
        preg_match('#href="([^"]+)"#', $html, $matches);

        return html_entity_decode($matches[1]);
    }

    private function tamperedUrlFrom(TemplatedEmail $message): string
    {
        $url = $this->signedUrlFrom($message);

        return (string) preg_replace('#(signature=)[^&]+#', '$1tampered', $url);
    }
}
