<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_outbox_pending', columns: ['published_at'])]
class OutboxEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /** null = still waiting to be published */
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\ManyToOne(inversedBy: 'outboxEvents')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Order $order = null;

    public function __construct(/** e.g. order.submitted */
        #[ORM\Column(length: 100)]
        private string $eventType,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $eventVersion,
        #[ORM\Column(type: Types::JSON)]
        private array $payload
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
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

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }
}