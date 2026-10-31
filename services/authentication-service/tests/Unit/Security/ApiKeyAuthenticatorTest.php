<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Event\UserLoginEvent;
use App\Repository\UserRepository;
use App\Security\ApiKeyAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class ApiKeyAuthenticatorTest extends TestCase
{
    public function testSuccessfulAuthenticationDispatchesTheLoginEventForTheAuthenticatedUser(): void
    {
        $user = (new User())->setEmail('person@example.com');

        $repository = $this->createStub(UserRepository::class);
        $repository->method('findOneBy')->willReturn($user);

        $dispatcher = new EventDispatcher();
        $dispatchedEvent = null;
        $dispatcher->addListener(
            UserLoginEvent::class,
            static function (UserLoginEvent $event) use (&$dispatchedEvent): void {
                $dispatchedEvent = $event;
            },
        );

        $authenticator = new ApiKeyAuthenticator($dispatcher, $repository);
        $request = Request::create('/api/me');
        $request->headers->set('X-AUTH-TOKEN', 'a-valid-token');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $authenticator->authenticate($request);
        $authenticator->onAuthenticationSuccess($request, $token, 'main');

        self::assertInstanceOf(UserLoginEvent::class, $dispatchedEvent);
        self::assertSame($user, $dispatchedEvent->getUser());
    }

    public function testAuthenticationRejectsAnUnknownToken(): void
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $authenticator = new ApiKeyAuthenticator(new EventDispatcher(), $repository);
        $request = Request::create('/api/me');
        $request->headers->set('X-AUTH-TOKEN', 'an-unknown-token');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid API token.');

        $authenticator->authenticate($request);
    }
}
