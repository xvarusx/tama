<?php

namespace App\Tests\Unit\Event;

use App\Entity\User;
use App\Event\UserRegisterEvent;
use PHPUnit\Framework\TestCase;

final class UserRegisterEventTest extends TestCase
{
    public function testGetUserReturnsTheRegisteredUser(): void
    {
        $user = (new User())->setEmail('person@example.com');

        $event = new UserRegisterEvent($user);

        self::assertSame($user, $event->getUser());
    }
}
