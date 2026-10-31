<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'contact_audit_entries')]
#[ORM\Index(name: 'idx_contact_audit_contact', fields: ['contactId'])]
#[ORM\Index(name: 'idx_contact_audit_user', fields: ['userId'])]
class ContactAuditEntry
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $contactId;

    #[ORM\Column]
    private int $userId;

    #[ORM\Column(length: 32)]
    private string $action;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    public function __construct(Uuid $contactId, int $userId, string $action)
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException('User IDs must be positive integers.');
        }

        $this->id = Uuid::v7();
        $this->contactId = $contactId;
        $this->userId = $userId;
        $this->action = $action;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getContactId(): Uuid
    {
        return $this->contactId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
