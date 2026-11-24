<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Channel;
use App\Enum\DeliveryStatus;
use App\Exceptions\InvalidDeliveryTransition;
use App\Traits\ShareableTrait;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'deliveries')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_deliveries_due', columns: ['status', 'next_attempt_at'])]
#[ORM\Index(name: 'idx_deliveries_stale', columns: ['status', 'sending_at'])]
#[ORM\Index(name: 'idx_deliveries_recipient_email', columns: ['recipient_email'])]
class Delivery
{
    use ShareableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /** @var Collection<int, DeliveryAttempt> */
    #[ORM\OneToMany(targetEntity: DeliveryAttempt::class, mappedBy: 'delivery', cascade: ['remove'])]
    private Collection $deliveryAttempts;

    #[ORM\Column(length: 20, enumType: DeliveryStatus::class)]
    private DeliveryStatus $status = DeliveryStatus::QUEUED;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $nextAttemptAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $sendingAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'deliveries')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Notification $notification,
        #[ORM\Column(length: 20, enumType: Channel::class)]
        private Channel $channel,
        #[ORM\Column(length: 180)]
        private string $recipientEmail,
        #[ORM\Column(length: 255)]
        private string $subject,
        #[ORM\Column(type: Types::TEXT)]
        private string $body,
    ) {
        $this->id = Uuid::v7();
        $this->nextAttemptAt = new DateTimeImmutable();
        $this->deliveryAttempts = new ArrayCollection();

        $this->notification->addDelivery($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getStatus(): DeliveryStatus
    {
        return $this->status;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getNextAttemptAt(): ?DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function getSendingAt(): ?DateTimeImmutable
    {
        return $this->sendingAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    /** @return Collection<int, DeliveryAttempt> */
    public function getDeliveryAttempts(): Collection
    {
        return $this->deliveryAttempts;
    }

    public function addDeliveryAttempt(DeliveryAttempt $attempt): void
    {
        if (!$this->deliveryAttempts->contains($attempt)) {
            $this->deliveryAttempts->add($attempt);
        }
    }

    /**
     * @throws InvalidDeliveryTransition when the delivery is neither queued nor waiting for a retry
     */
    public function markSending(): void
    {
        if (!\in_array($this->status, [DeliveryStatus::QUEUED, DeliveryStatus::RETRY_WAITING], true)) {
            throw new InvalidDeliveryTransition($this->status, 'mark as sending');
        }

        $this->status = DeliveryStatus::SENDING;
        $this->sendingAt = new DateTimeImmutable();
        ++$this->attempts;
    }

    /**
     * @throws InvalidDeliveryTransition when the delivery is not being sent
     */
    public function markSent(): void
    {
        if ($this->status !== DeliveryStatus::SENDING) {
            throw new InvalidDeliveryTransition($this->status, 'mark as sent');
        }

        $this->status = DeliveryStatus::SENT;
        $this->sentAt = new DateTimeImmutable();
        $this->nextAttemptAt = null;
        $this->lastError = null;
    }

    /**
     * @throws InvalidDeliveryTransition when the delivery is not being sent
     */
    public function scheduleRetry(int $backoffSeconds, string $error): void
    {
        if ($this->status !== DeliveryStatus::SENDING) {
            throw new InvalidDeliveryTransition($this->status, 'schedule a retry for');
        }

        $this->status = DeliveryStatus::RETRY_WAITING;
        $this->nextAttemptAt = new DateTimeImmutable()->modify(sprintf('+%d seconds', $backoffSeconds));
        $this->lastError = $error;
    }

    /**
     * @throws InvalidDeliveryTransition when the delivery is not being sent
     */
    public function markFailed(string $error): void
    {
        if ($this->status !== DeliveryStatus::SENDING) {
            throw new InvalidDeliveryTransition($this->status, 'mark as failed');
        }

        $this->status = DeliveryStatus::FAILED;
        $this->lastError = $error;
        $this->nextAttemptAt = null;
    }

    /**
     * Reclaims a delivery whose worker died after claiming it.
     *
     * @throws InvalidDeliveryTransition when the delivery is not being sent
     */
    public function reclaimStale(): void
    {
        if ($this->status !== DeliveryStatus::SENDING) {
            throw new InvalidDeliveryTransition($this->status, 'reclaim');
        }

        $this->status = DeliveryStatus::RETRY_WAITING;
        $this->nextAttemptAt = new DateTimeImmutable();
        $this->lastError = 'Reclaimed after timeout';
        ++$this->attempts;
    }

    /**
     * @throws InvalidDeliveryTransition unless an administrator is requeueing a failed delivery
     */
    public function requeue(): void
    {
        if ($this->status !== DeliveryStatus::FAILED) {
            throw new InvalidDeliveryTransition($this->status, 'requeue');
        }

        $this->status = DeliveryStatus::QUEUED;
        $this->attempts = 0;
        $this->lastError = null;
        $this->sendingAt = null;
        $this->nextAttemptAt = new DateTimeImmutable();
    }
}
