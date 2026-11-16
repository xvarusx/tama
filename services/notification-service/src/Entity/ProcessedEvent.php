<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'processed_events')]
class ProcessedEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $eventId;

    #[ORM\Column]
    private DateTimeImmutable $processedAt;

    public function __construct(string $eventId, #[ORM\Column(length: 100)]
        private string $source)
    {
        $this->eventId = Uuid::fromString($eventId);
        $this->processedAt = new DateTimeImmutable();
    }

    public function getEventId(): Uuid
    {
        return $this->eventId;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getProcessedAt(): DateTimeImmutable
    {
        return $this->processedAt;
    }
}
