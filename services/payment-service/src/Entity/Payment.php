<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentDirection;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Event\PaymentAllocatedV1;
use App\Event\PaymentCanceledV1;
use App\Event\PaymentCompletedV1;
use App\Event\PaymentCreatedV1;
use App\Event\PaymentFailedV1;
use App\Event\PaymentReversedV1;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Repository\PaymentRepository')]
#[ORM\Index(name: 'idx_payment_status', columns: ['status'])]
#[ORM\Index(name: 'idx_payment_direction', columns: ['direction'])]
#[ORM\Index(name: 'idx_payment_party', columns: ['party_id'])]
#[ORM\Index(name: 'idx_payment_method', columns: ['method'])]
#[ORM\Index(name: 'idx_payment_paid_at', columns: ['paid_at'])]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    private string $number;

    #[ORM\Column(type: Types::STRING, enumType: PaymentDirection::class)]
    private PaymentDirection $direction;

    #[ORM\Column(type: Types::STRING, enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column(type: Types::STRING, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::PENDING;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $partyId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $partyName;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::INTEGER)]
    private int $amount;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    /** @var PaymentAllocation[] */
    #[ORM\OneToMany(targetEntity: PaymentAllocation::class, cascade: ['persist', 'remove'], mappedBy: 'payment', orphanRemoval: true)]
    private Collection $allocations;

    /** @var object[] Domain events pulled after commit */
    private array $events = [];

    public function __construct(string $number, PaymentDirection $direction, PaymentMethod $method, string $partyId, string $partyName, string $currency, int $amount)
    {
        $this->id = Uuid::v7();
        $this->number = $number;
        $this->direction = $direction;
        $this->method = $method;
        $this->partyId = $partyId;
        $this->partyName = $partyName;
        $this->currency = $currency;
        $this->amount = $amount;
        $this->createdAt = new \DateTimeImmutable();
        $this->allocations = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getDirection(): PaymentDirection
    {
        return $this->direction;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getPartyId(): string
    {
        return $this->partyId;
    }

    public function getPartyName(): string
    {
        return $this->partyName;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    /**
     * Used by imports and fixtures to restore a historical settlement time.
     * Normal runtime transitions go through confirm().
     */
    public function setPaidAt(?\DateTimeInterface $paidAt): self
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    /** @return PaymentAllocation[] */
    public function getAllocations(): iterable
    {
        return $this->allocations;
    }

    /**
     * Allocate a portion of this payment to an installment.
     */
    public function allocate(Installment $installment, InvoiceReference $invoice, int $amount): PaymentAllocation
    {
        $allocation = new PaymentAllocation($this, $installment, $invoice, $amount);
        $this->allocations->add($allocation);
        $this->events[] = PaymentAllocatedV1::toOutbox($this, $installment, $allocation);
        return $allocation;
    }

    /**
     * Confirm the payment (cheque cleared, transfer settled).
     */
    public function confirm(): void
    {
        if ($this->status !== PaymentStatus::PENDING) {
            throw new \LogicException('Only a PENDING payment can be confirmed.');
        }
        $this->status = PaymentStatus::COMPLETED;
        $this->paidAt = new \DateTimeImmutable();
        $this->events[] = PaymentCompletedV1::toOutbox($this);
    }

    /**
     * Fail the payment (cheque bounced, transfer rejected).
     */
    public function fail(string $reason): void
    {
        if ($this->status !== PaymentStatus::PENDING) {
            throw new \LogicException('Only a PENDING payment can be failed.');
        }
        $this->status = PaymentStatus::FAILED;
        $this->events[] = PaymentFailedV1::toOutbox($this, $reason);
    }

    /**
     * Cancel the payment.
     */
    public function cancel(): void
    {
        if ($this->status === PaymentStatus::COMPLETED) {
            throw new \LogicException('A COMPLETED payment cannot be canceled.');
        }
        if ($this->status === PaymentStatus::REVERSED) {
            throw new \LogicException('A REVERSED payment cannot be canceled.');
        }
        $this->status = PaymentStatus::CANCELED;
        $this->events[] = PaymentCanceledV1::toOutbox($this);
    }

    /**
     * Reverse a completed payment.
     */
    public function reverse(string $reason): void
    {
        if ($this->status !== PaymentStatus::COMPLETED) {
            throw new \LogicException('Only a COMPLETED payment can be reversed.');
        }
        $this->status = PaymentStatus::REVERSED;
        $this->events[] = PaymentReversedV1::toOutbox($this, $reason);
    }

    /**
     * Pull the domain events recorded on this entity.
     *
     * @return object[]
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];
        return $events;
    }
}