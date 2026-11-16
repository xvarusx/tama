<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationStatus;
use App\Enum\Severity;
use App\Exceptions\InvalidNotificationTransition;
use App\Traits\ShareableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_notifications_inbox', columns: ['recipient_id', 'visible_in_app', 'status', 'created_at'])]
#[ORM\UniqueConstraint(name: 'uniq_notifications_recipient_dedup', columns: ['recipient_id', 'dedup_key'])]
class Notification
{
    use ShareableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /** @var Collection<int, Delivery> */
    #[ORM\OneToMany(targetEntity: Delivery::class, mappedBy: 'notification', cascade: ['remove'])]
    private Collection $deliveries;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $readAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    public function __construct(
        #[ORM\Column]
        private int $recipientId,
        #[ORM\Column(length: 120)]
        private string $type,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
        #[ORM\Column(length: 20, enumType: Severity::class)]
        private Severity $severity,
        #[ORM\Column]
        private bool $visibleInApp,
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $link = null,
        #[ORM\Column(length: 190, nullable: true)]
        private ?string $dedupKey = null,
        #[ORM\Column(length: 20, enumType: NotificationStatus::class)]
        private NotificationStatus $status = NotificationStatus::UNREAD,
    ) {
        $this->id = Uuid::v7();
        $this->deliveries = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRecipientId(): int
    {
        return $this->recipientId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getSeverity(): Severity
    {
        return $this->severity;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function isVisibleInApp(): bool
    {
        return $this->visibleInApp;
    }

    public function getVisibleInApp(): bool
    {
        return $this->visibleInApp;
    }

    public function getDedupKey(): ?string
    {
        return $this->dedupKey;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isUnread(): bool
    {
        return $this->status === NotificationStatus::UNREAD;
    }

    /**
     * @throws InvalidNotificationTransition when the notification is archived
     */
    public function markAsRead(): void
    {
        if ($this->status === NotificationStatus::ARCHIVED) {
            throw new InvalidNotificationTransition($this->status, 'mark as read');
        }

        if ($this->status === NotificationStatus::READ) {
            return;
        }

        $this->status = NotificationStatus::READ;
        $this->readAt = new DateTimeImmutable();
    }

    public function archive(): void
    {
        if ($this->status === NotificationStatus::ARCHIVED) {
            return;
        }

        $this->status = NotificationStatus::ARCHIVED;
        $this->archivedAt = new DateTimeImmutable();
    }

    /** @return Collection<int, Delivery> */
    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    public function addDelivery(Delivery $delivery): void
    {
        if (!$this->deliveries->contains($delivery)) {
            $this->deliveries->add($delivery);
        }
    }

    public function removeDelivery(Delivery $delivery): void
    {
        $this->deliveries->removeElement($delivery);
    }
}
