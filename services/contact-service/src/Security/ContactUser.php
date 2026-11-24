<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ContactUser implements UserInterface
{
    public function __construct(private string $id)
    {
        if (!Uuid::isValid($id)) {
            throw new \InvalidArgumentException('User IDs must be valid UUIDs.');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return $this->id;
    }

    public function eraseCredentials(): void {}
}
