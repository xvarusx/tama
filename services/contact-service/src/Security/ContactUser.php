<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ContactUser implements UserInterface
{
    public function __construct(private int $id)
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('User IDs must be positive integers.');
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->id;
    }

    public function eraseCredentials(): void
    {
    }
}
