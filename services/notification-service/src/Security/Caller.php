<?php

declare(strict_types=1);

namespace App\Security;

/** Identity of the caller resolved through the authentication-service /me endpoint. */
final readonly class Caller
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

    /** @return array{id: string, email: string, fullName: string, roles: list<string>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'fullName' => $this->fullName,
            'roles' => $this->roles,
        ];
    }
}
