<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Security\AuthServiceClient;
use App\Security\Caller;
use App\Security\Recipient;
use Throwable;

/**
 * Test double for the authentication boundary (SDD ruling R5).
 * Configurable caller/audience, records calls, never touches the network.
 */
final class FakeAuthServiceClient extends AuthServiceClient
{
    private ?Caller $caller = null;

    /** @var list<Recipient> */
    private array $audience = [];

    private ?Throwable $audienceFailure = null;

    /** @var list<string> */
    public array $authenticateTokens = [];

    /** @var list<array<string, mixed>> */
    public array $audienceCalls = [];

    public function __construct()
    {
        // Intentionally does not call the parent: the HTTP client and cache
        // are never used in tests.
    }

    public function setCaller(?Caller $caller): void
    {
        $this->caller = $caller;
    }

    /** @param list<Recipient> $recipients */
    public function setAudience(array $recipients): void
    {
        $this->audience = $recipients;
    }

    public function setAudienceFailure(?Throwable $failure): void
    {
        $this->audienceFailure = $failure;
    }

    public function authenticate(string $token): ?Caller
    {
        $this->authenticateTokens[] = $token;

        return $this->caller;
    }

    public function resolveAudience(array $audience): array
    {
        $this->audienceCalls[] = $audience;

        if ($this->audienceFailure !== null) {
            throw $this->audienceFailure;
        }

        return $this->audience;
    }
}
