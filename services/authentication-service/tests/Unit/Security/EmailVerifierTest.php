<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\EmailVerifier;
use App\Tests\Support\FakeVerifyEmailHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use SymfonyCasts\Bundle\VerifyEmail\Exception\InvalidSignatureException;

final class EmailVerifierTest extends TestCase
{
    public function testSendEmailConfirmationAddsTheSignedUrlAndExpiryContextToTheEmail(): void
    {
        $signedUrl = 'https://example.com/api/authenticator/verify/email?id=42&token=token&expires=123';
        $helper = new FakeVerifyEmailHelper($signedUrl);

        $mailer = $this->capturingMailer();
        $verifier = new EmailVerifier($helper, $mailer, $this->createStub(EntityManagerInterface::class));

        $email = $this->confirmationEmail();
        $verifier->sendEmailConfirmation('app_verify_email', $this->userWithId(42), $email);

        self::assertSame($email, $mailer->sent);
        self::assertSame($signedUrl, $email->getContext()['signedUrl']);
        self::assertSame('%count% hour|%count% hours', $email->getContext()['expiresAtMessageKey']);
        self::assertSame(['%count%' => 1], $email->getContext()['expiresAtMessageData']);
        self::assertSame([[
            'routeName' => 'app_verify_email',
            'userId' => '42',
            'userEmail' => 'person@example.com',
            'extraParams' => ['id' => '42'],
        ]], $helper->generateSignatureCalls);
    }

    public function testHandleEmailConfirmationMarksTheUserAsVerified(): void
    {
        $helper = new FakeVerifyEmailHelper();
        $verifier = new EmailVerifier(
            $helper,
            $this->capturingMailer(),
            $this->createStub(EntityManagerInterface::class),
        );

        $user = $this->userWithId(42);
        $request = Request::create('/api/authenticator/verify/email');
        $verifier->handleEmailConfirmation($request, $user);

        self::assertTrue($user->isVerified());
        self::assertSame([[
            'request' => $request,
            'userId' => '42',
            'userEmail' => 'person@example.com',
        ]], $helper->validationCalls);
    }

    public function testHandleEmailConfirmationDoesNotVerifyTheUserWhenTheSignatureIsInvalid(): void
    {
        $helper = new FakeVerifyEmailHelper();
        $helper->validationException = new InvalidSignatureException();

        $verifier = new EmailVerifier(
            $helper,
            $this->capturingMailer(),
            $this->createStub(EntityManagerInterface::class),
        );

        $user = $this->userWithId(42);

        try {
            $verifier->handleEmailConfirmation(Request::create('/api/authenticator/verify/email'), $user);
            self::fail('An invalid signature must abort the confirmation.');
        } catch (InvalidSignatureException) {
            self::assertFalse($user->isVerified());
        }
    }

    private function confirmationEmail(): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address('no-replay@oussemaharrbi.tn', 'oh Mail Bot'))
            ->to('person@example.com')
            ->subject('Please Confirm your Email')
            ->htmlTemplate('registration/confirmation_email.html.twig');
    }

    private function userWithId(int $id): User
    {
        $user = (new User())->setEmail('person@example.com');

        $idProperty = new \ReflectionProperty(User::class, 'id');
        $idProperty->setValue($user, $id);

        return $user;
    }

    private function capturingMailer(): MailerInterface
    {
        return new class implements MailerInterface {
            public ?TemplatedEmail $sent = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if (!$message instanceof TemplatedEmail) {
                    throw new \LogicException('Expected a templated confirmation email.');
                }

                $this->sent = $message;
            }
        };
    }
}
