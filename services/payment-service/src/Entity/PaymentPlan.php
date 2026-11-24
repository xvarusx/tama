<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Repository\PaymentPlanRepository')]
#[ORM\Index(name: 'idx_payment_plan_status', columns: ['status'])]
class PaymentPlan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $invoiceId;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalAmount;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status = 'ACTIVE';

    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Installment[] */
    #[ORM\OneToMany(targetEntity: Installment::class, cascade: ['persist', 'remove'], mappedBy: 'paymentPlan', orphanRemoval: true)]
    private Collection $installments;

    public function __construct(string $invoiceId, int $totalAmount, string $currency)
    {
        $this->id = Uuid::v7();
        $this->invoiceId = $invoiceId;
        $this->totalAmount = $totalAmount;
        $this->currency = $currency;
        $this->createdAt = new \DateTimeImmutable();
        $this->installments = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    /** @return Installment[] */
    public function getInstallments(): iterable
    {
        return $this->installments;
    }

    public function addInstallment(\DateTimeInterface $dueDate, int $amount): Installment
    {
        $nextSequence = $this->installments->count() + 1;
        $installment = new Installment($this, $nextSequence, $dueDate, $amount);
        $this->installments->add($installment);
        return $installment;
    }

    /**
     * Reschedule unpaid installments by moving their due dates forward.
     *
     * @param array<string, mixed> $unpaidInstallments Map of installmentId => newDueDate
     */
    public function reschedule(array $unpaidInstallments): void
    {
        foreach ($this->installments as $installment) {
            $installmentId = $installment->getId()->toString();
            if (isset($unpaidInstallments[$installmentId])) {
                $installment->setDueDate($unpaidInstallments[$installmentId]);
            }
        }
    }

    public function cancel(): void
    {
        $this->status = 'CANCELED';
        foreach ($this->installments as $installment) {
            $installment->setStatus(\App\Enum\InstallmentStatus::CANCELED);
        }
    }

    public function isComplete(): bool
    {
        foreach ($this->installments as $installment) {
            if ($installment->getStatus() !== \App\Enum\InstallmentStatus::PAID) {
                return false;
            }
        }
        return true;
    }
}