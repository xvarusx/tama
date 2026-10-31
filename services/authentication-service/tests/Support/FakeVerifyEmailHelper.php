<?php

namespace App\Tests\Support;

use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class FakeVerifyEmailHelper implements VerifyEmailHelperInterface
{
    /**
     * @var list<array{routeName: string, userId: string, userEmail: string, extraParams: array<string, string>}>
     */
    public array $generateSignatureCalls = [];

    /**
     * @var list<array{request: Request, userId: string, userEmail: string}>
     */
    public array $validationCalls = [];

    public ?\Throwable $validationException = null;

    public function __construct(private readonly string $signedUrl = 'https://example.com/api/authenticator/verify/email')
    {
    }

    public function generateSignature(string $routeName, string $userId, string $userEmail, array $extraParams = []): VerifyEmailSignatureComponents
    {
        $this->generateSignatureCalls[] = compact('routeName', 'userId', 'userEmail', 'extraParams');

        return new VerifyEmailSignatureComponents(new \DateTimeImmutable('+1 hour'), $this->signedUrl, time());
    }

    public function validateEmailConfirmation(string $signedUrl, string $userId, string $userEmail): void
    {
    }

    public function validateEmailConfirmationFromRequest(Request $request, string $userId, string $userEmail): void
    {
        $this->validationCalls[] = compact('request', 'userId', 'userEmail');

        if (null !== $this->validationException) {
            throw $this->validationException;
        }
    }
}
