<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_outbox_pending', columns: ['published_at'])]
class OutboxEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /** null = still waiting to be published */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    public function __construct(/** e.g. product.created */
        #[ORM\Column(length: 100)]
        private string $eventType,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $eventVersion,
        #[ORM\Column(type: Types::JSON)]
        private array $payload
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getEventVersion(): int
    {
        return $this->eventVersion;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function markPublished(): void
    {
        $this->publishedAt = new DateTimeImmutable();
    }
}
