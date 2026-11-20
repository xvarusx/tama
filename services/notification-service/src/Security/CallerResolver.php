<?php

declare(strict_types=1);

namespace App\Security;

use App\Exceptions\ForbiddenException;
use App\Exceptions\UnauthenticatedException;
use Symfony\Component\HttpFoundation\Request;

/** Turns the gateway-validated X-AUTH-TOKEN header into a Caller value object. */
final readonly class CallerResolver
{
    public function __construct(private AuthServiceClient $authServiceClient) {}

    public function resolve(Request $request): Caller
    {
        $token = $request->headers->get('X-AUTH-TOKEN');
        if ($token === null || trim($token) === '') {
            throw new UnauthenticatedException();
        }

        $caller = $this->authServiceClient->authenticate($token);
        if ($caller === null) {
            throw new UnauthenticatedException();
        }

        return $caller;
    }

    public function requireAdmin(Caller $caller): void
    {
        if (!$caller->hasRole('ROLE_ADMIN')) {
            throw new ForbiddenException();
        }
    }
}
