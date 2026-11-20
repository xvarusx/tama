<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Exceptions\ForbiddenException;
use App\Exceptions\UnauthenticatedException;
use App\Security\AuthServiceClient;
use App\Security\Caller;
use App\Security\CallerResolver;
use App\Utils\DemoUserUuids;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CallerResolverTest extends TestCase
{
    public function testCallerResolverRejectsMissingToken(): void
    {
        $client = $this->createMock(AuthServiceClient::class);
        $client->expects(self::never())->method('authenticate');

        $this->expectException(UnauthenticatedException::class);

        new CallerResolver($client)->resolve(new Request());
    }

    public function testCallerResolverRejectsInvalidToken(): void
    {
        $client = $this->createMock(AuthServiceClient::class);
        $client->expects(self::once())->method('authenticate')->with('expired')->willReturn(null);

        $request = Request::create('/api/v1/notifications', server: ['HTTP_X_AUTH_TOKEN' => 'expired']);

        $this->expectException(UnauthenticatedException::class);

        new CallerResolver($client)->resolve($request);
    }

    public function testCallerResolverReturnsAuthenticatedCaller(): void
    {
        $caller = new Caller(DemoUserUuids::forNumber(1), 'jane@example.com', 'Jane Doe', ['ROLE_USER']);
        $client = $this->createMock(AuthServiceClient::class);
        $client->expects(self::once())->method('authenticate')->with('valid')->willReturn($caller);

        $request = Request::create('/api/v1/notifications', server: ['HTTP_X_AUTH_TOKEN' => 'valid']);

        self::assertSame($caller, new CallerResolver($client)->resolve($request));
    }

    public function testRequireAdminRejectsNonAdmin(): void
    {
        $caller = new Caller(DemoUserUuids::forNumber(1), 'jane@example.com', 'Jane Doe', ['ROLE_USER']);

        $this->expectException(ForbiddenException::class);

        new CallerResolver(self::createStub(AuthServiceClient::class))->requireAdmin($caller);
    }

    public function testRequireAdminAcceptsAdmin(): void
    {
        $caller = new Caller(DemoUserUuids::forNumber(2), 'admin@example.com', 'Admin', ['ROLE_USER', 'ROLE_ADMIN']);

        new CallerResolver(self::createStub(AuthServiceClient::class))->requireAdmin($caller);

        self::assertTrue($caller->hasRole('ROLE_ADMIN'));
    }
}
