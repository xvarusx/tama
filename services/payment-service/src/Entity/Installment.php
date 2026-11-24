<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InstallmentStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Repository\InstallmentRepository')]
#[ORM\Index(name: 'idx_installment_plan', columns: ['payment_plan_id'])]
#[ORM\Index(name: 'idx_installment_sequence', columns: ['sequence'])]
#[ORM\Index(name: 'idx_installment_status', columns: ['status'])]
#[ORM\Index(name: 'idx_installment_due_date', columns: ['due_date'])]
class Installment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: PaymentPlan::class, inversedBy: 'installments')]
    #[ORM\JoinColumn(nullable: false)]
    private PaymentPlan $paymentPlan;

    #[ORM\Column(type: Types::INTEGER)]
    private int $sequence;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeInterface $dueDate;

    #[ORM\Column(type: Types::INTEGER)]
    private int $amount;

    #[ORM\Column(type: Types::INTEGER)]
    private int $paidAmount = 0;

    #[ORM\Column(type: Types::STRING, enumType: InstallmentStatus::class)]
    private InstallmentStatus $status = InstallmentStatus::UNPAID;

    /** @var Reminder[] */
    #[ORM\OneToMany(targetEntity: Reminder::class, cascade: ['persist'], orphanRemoval: true, mappedBy: 'installment')]
    private Collection $reminders;

    public function __construct(PaymentPlan $paymentPlan, int $sequence, \DateTimeInterface $dueDate, int $amount)
    {
        $this->id = Uuid::v7();
        $this->paymentPlan = $paymentPlan;
        $this->sequence = $sequence;
        $this->dueDate = $dueDate;
        $this->amount = $amount;
        $this->reminders = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPaymentPlan(): PaymentPlan
    {
        return $this->paymentPlan;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getDueDate(): \DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(int $paidAmount): self
    {
        $this->paidAmount = $paidAmount;
        return $this;
    }

    public function getStatus(): InstallmentStatus
    {
        return $this->status;
    }

    public function setStatus(InstallmentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    /** @return Reminder[] */
    public function getReminders(): iterable
    {
        return $this->reminders;
    }

    public function addReminder(Reminder $reminder): self
    {
        $this->reminders->add($reminder);
        return $this;
    }

    /**
     * Apply a payment to this installment.
     *
     * Returns the amount actually applied.
     */
    public function applyPayment(int $amount): int
    {
        if ($this->status === InstallmentStatus::PAID || $this->status === InstallmentStatus::CANCELED) {
            return 0;
        }

        $applied = min($amount, $this->getRemaining());
        $this->paidAmount += $applied;

        if ($this->paidAmount >= $this->amount) {
            $this->status = InstallmentStatus::PAID;
        } elseif ($this->paidAmount > 0) {
            $this->status = InstallmentStatus::PARTIALLY_PAID;
        }

        return $applied;
    }

    public function getRemaining(): int
    {
        return max(0, $this->amount - $this->paidAmount);
    }

    public function isOverdue(\DateTimeInterface $today): bool
    {
        return $this->status !== InstallmentStatus::PAID
            && $this->status !== InstallmentStatus::CANCELED
            && $this->dueDate < $today;
    }
}