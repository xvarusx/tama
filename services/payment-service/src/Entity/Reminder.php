<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReminderKind;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Repository\ReminderRepository')]
#[ORM\Index(name: 'idx_reminder_kind', columns: ['kind'])]
#[ORM\Index(name: 'idx_reminder_scheduled_for', columns: ['scheduled_for'])]
#[ORM\Index(name: 'idx_reminder_status', columns: ['status'])]
class Reminder
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Installment::class, inversedBy: 'reminders')]
    #[ORM\JoinColumn(nullable: false)]
    private Installment $installment;

    #[ORM\Column(type: Types::STRING, enumType: ReminderKind::class)]
    private ReminderKind $kind;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeInterface $scheduledFor;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status = 'PENDING';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getInstallment(): Installment
    {
        return $this->installment;
    }

    public function setInstallment(Installment $installment): self
    {
        $this->installment = $installment;
        return $this;
    }

    public function getKind(): ReminderKind
    {
        return $this->kind;
    }

    public function setKind(ReminderKind $kind): self
    {
        $this->kind = $kind;
        return $this;
    }

    public function getScheduledFor(): \DateTimeInterface
    {
        return $this->scheduledFor;
    }

    public function setScheduledFor(\DateTimeInterface $scheduledFor): self
    {
        $this->scheduledFor = $scheduledFor;
        return $this;
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

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function markSent(): self
    {
        $this->status = 'SENT';
        $this->sentAt = new \DateTimeImmutable();
        return $this;
    }

    public function cancel(): self
    {
        $this->status = 'CANCELED';
        return $this;
    }
}