<?php

declare(strict_types=1);

namespace App\Entity;

use App\Event\ContactBlacklisted;
use App\Event\ContactDeactivated;
use App\Event\ContactReinstated;
use App\Event\ContactUpdated;
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

    public function __construct(/** e.g. contact.created */
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

    public static function fromEvent(object $event): self
    {
        if ($event instanceof ContactBlacklisted) {
            $contact = $event->contact;
            return new self('contact.blacklisted', 1, [
                'contactId' => $contact->getId()->toRfc4122(),
                'contactType' => $contact->getContactType()->value,
                'reason' => $event->reason,
            ]);
        }

        if ($event instanceof ContactDeactivated) {
            $contact = $event->contact;
            return new self('contact.deactivated', 1, [
                'contactId' => $contact->getId()->toRfc4122(),
                'contactType' => $contact->getContactType()->value,
            ]);
        }

        if ($event instanceof ContactReinstated) {
            $contact = $event->contact;
            return new self('contact.reinstated', 1, [
                'contactId' => $contact->getId()->toRfc4122(),
                'contactType' => $contact->getContactType()->value,
            ]);
        }

        if ($event instanceof ContactUpdated) {
            $contact = $event->contact;
            return new self('contact.updated', 1, [
                'contactId' => $contact->getId()->toRfc4122(),
                'name' => $contact->getName(),
                'type' => $contact->getContactType()->value,
                'changedFields' => $event->changedFields,
            ]);
        }

        throw new \InvalidArgumentException(sprintf('Unsupported event type: %s', get_class($event)));
    }
}
