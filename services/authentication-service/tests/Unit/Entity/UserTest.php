<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testUserIdentifierIsTheEmailAddress(): void
    {
        $user = (new User())->setEmail('person@example.com');

        self::assertSame('person@example.com', $user->getUserIdentifier());
    }

    public function testUserAlwaysHasTheUserRoleAndDoesNotDuplicateRoles(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN', 'ROLE_USER', 'ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testNewUserIsNotVerified(): void
    {
        self::assertFalse((new User())->isVerified());
    }
}
