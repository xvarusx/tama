<?php

declare(strict_types=1);

namespace App\Security;

/** A user returned by the authentication-service internal directory. */
final readonly class Recipient
{
    /** @param list<string> $roles */
    public function __construct(
        public string $id,
        public string $email,
        public string $fullName,
        public array $roles,
    ) {}

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }
}
