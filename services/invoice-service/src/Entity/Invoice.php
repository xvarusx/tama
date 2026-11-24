<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\PaymentResult;
use App\Event\CreditNoteIssued;
use App\Event\InvoiceCreated;
use App\Event\InvoiceDomainEvent;
use App\Event\InvoiceIssued;
use App\Event\InvoicePaid;
use App\Event\InvoiceVoided;
use App\Exceptions\CreditNoteExceedsBalanceException;
use App\Exceptions\CurrencyMismatchException;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Exceptions\InvoiceNotEditableException;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'invoices')]
#[ORM\UniqueConstraint(name: 'uniq_invoices_number', columns: ['number'])]
#[ORM\UniqueConstraint(name: 'uniq_invoices_active_order_key', columns: ['active_order_key'])]
#[ORM\Index(name: 'idx_invoices_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_invoices_status', columns: ['status'])]
#[ORM\Index(name: 'idx_invoices_type', columns: ['type'])]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(length: 20, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status;

    /** Equals orderId while the invoice is active; null once voided. Uniqueness is what blocks a second active invoice. */
    #[ORM\Column(length: 36, unique: true, nullable: true)]
    private ?string $activeOrderKey;

    #[ORM\Column(length: 255)]
    private string $partyName;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $issueDate = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $dueDate = null;

    /** minor units */
    #[ORM\Column]
    private int $amountPaid = 0;

    #[ORM\Column]
    private bool $overpaymentFlagged = false;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    /** @var Collection<int, CreditNote> */
    #[ORM\OneToMany(targetEntity: CreditNote::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $creditNotes;

    /** @var Collection<int, InvoicePayment> */
    #[ORM\OneToMany(targetEntity: InvoicePayment::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $payments;

    /** @var list<InvoiceDomainEvent> */
    private array $events = [];

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $orderId,
        #[ORM\Column(length: 20, enumType: InvoiceType::class)]
        private InvoiceType $type,
        #[ORM\Column(type: 'uuid')]
        private Uuid $partyId,
        string $partyName,
        string $currency,
    ) {
        if (trim($partyName) === '') {
            throw new InvalidArgumentException('Party name must not be blank.');
        }
        if (\strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }

        $this->id = Uuid::v7();
        $this->partyName = $partyName;
        $this->currency = $currency;
        $this->status = InvoiceStatus::DRAFT;
        $this->activeOrderKey = $this->orderId->toRfc4122();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->lines = new ArrayCollection();
        $this->creditNotes = new ArrayCollection();
        $this->payments = new ArrayCollection();

        $this->record(new InvoiceCreated($this->id->toRfc4122(), $this->orderId->toRfc4122()));
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getType(): InvoiceType
    {
        return $this->type;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function getOrderId(): Uuid
    {
        return $this->orderId;
    }

    public function getActiveOrderKey(): ?string
    {
        return $this->activeOrderKey;
    }

    public function getPartyId(): Uuid
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

    public function getIssueDate(): ?DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function getDueDate(): ?DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getAmountPaid(): int
    {
        return $this->amountPaid;
    }

    public function isOverpaymentFlagged(): bool
    {
        return $this->overpaymentFlagged;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, InvoiceLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    /** @return Collection<int, CreditNote> */
    public function getCreditNotes(): Collection
    {
        return $this->creditNotes;
    }

    /** @return Collection<int, InvoicePayment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function setPartyName(string $partyName): void
    {
        $this->assertEditable();
        if (trim($partyName) === '') {
            throw new InvalidArgumentException('Party name must not be blank.');
        }

        $this->partyName = $partyName;
        $this->touch();
    }

    public function setCurrency(string $currency): void
    {
        $this->assertEditable();
        if (\strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }

        $this->currency = $currency;
        $this->touch();
    }

    public function addLine(InvoiceLine $line): void
    {
        $this->assertEditable();
        $line->setInvoice($this);
        $line->setPosition($this->lines->count());
        $this->lines->add($line);
        $this->touch();
    }

    /** @param list<InvoiceLine> $lines */
    public function replaceLines(array $lines): void
    {
        $this->assertEditable();
        $this->lines->clear();

        foreach ($lines as $line) {
            $this->addLine($line);
        }
    }

    public function issue(string $number, DateTimeImmutable $dueDate): void
    {
        if ($this->status !== InvoiceStatus::DRAFT) {
            throw new InvalidInvoiceTransitionException(sprintf(
                'Only a draft invoice can be issued; this invoice is "%s".',
                $this->status->value,
            ));
        }
        if ($this->lines->isEmpty()) {
            throw new InvalidInvoiceTransitionException('An invoice needs at least one line before it can be issued.');
        }

        $today = new DateTimeImmutable('today');
        if ($dueDate < $today) {
            throw new InvalidInvoiceTransitionException('The due date must not be before the issue date.');
        }

        $this->number = $number;
        $this->issueDate = $today;
        $this->dueDate = $dueDate;
        $this->status = InvoiceStatus::ISSUED;
        $this->touch();

        $this->record(new InvoiceIssued($this->id->toRfc4122(), $number));
    }

    public function void(string $reason): void
    {
        $voidable = $this->status === InvoiceStatus::DRAFT
            || ($this->status === InvoiceStatus::ISSUED && $this->amountPaid === 0);

        if (!$voidable) {
            throw new InvalidInvoiceTransitionException(sprintf(
                'An invoice with status "%s" cannot be voided.',
                $this->status->value,
            ));
        }
        if (trim($reason) === '') {
            throw new InvalidInvoiceTransitionException('A void reason is required.');
        }

        $this->status = InvoiceStatus::VOIDED;
        $this->activeOrderKey = null;
        $this->touch();

        $this->record(new InvoiceVoided($this->id->toRfc4122(), $reason));
    }

    public function issueCreditNote(int $amount, string $reason): CreditNote
    {
        if ($this->status === InvoiceStatus::DRAFT || $this->status === InvoiceStatus::VOIDED) {
            throw new InvalidInvoiceTransitionException(sprintf(
                'A credit note cannot be issued on a "%s" invoice.',
                $this->status->value,
            ));
        }

        $remaining = $this->getTotal() - $this->getCreditedAmount();
        if ($amount <= 0 || $amount > $remaining) {
            throw new CreditNoteExceedsBalanceException($amount, $remaining);
        }

        $number = sprintf('%s-CN%d', $this->number ?? 'DRAFT', $this->creditNotes->count() + 1);
        $creditNote = new CreditNote($this, $number, $amount, $reason);
        $this->creditNotes->add($creditNote);
        $this->touch();

        $this->record(new CreditNoteIssued($this->id->toRfc4122(), $amount));

        return $creditNote;
    }

    public function applyPayment(Uuid $paymentId, int $amount, string $currency): PaymentResult
    {
        // Replaying a payment is a no-op even after the invoice reached a
        // final state, so redelivery stays idempotent.
        foreach ($this->payments as $payment) {
            if ($payment->getPaymentId()->equals($paymentId)) {
                return PaymentResult::DUPLICATE;
            }
        }

        if (!\in_array($this->status, [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID], true)) {
            throw new InvalidInvoiceTransitionException(sprintf(
                'A payment cannot be applied to a "%s" invoice.',
                $this->status->value,
            ));
        }
        if ($currency !== $this->currency) {
            throw new CurrencyMismatchException($this->currency, $currency);
        }
        if ($amount <= 0) {
            throw new InvalidInvoiceTransitionException('A payment amount must be positive.');
        }

        if ($amount > $this->getBalance()) {
            $this->payments->add(new InvoicePayment($this, $paymentId, $amount, $currency, false));
            $this->overpaymentFlagged = true;
            $this->touch();

            return PaymentResult::FLAGGED;
        }

        $this->payments->add(new InvoicePayment($this, $paymentId, $amount, $currency, true));
        $this->amountPaid += $amount;

        if ($this->getBalance() === 0) {
            $this->status = InvoiceStatus::PAID;
            $this->record(new InvoicePaid($this->id->toRfc4122()));
        } else {
            $this->status = InvoiceStatus::PARTIALLY_PAID;
        }

        $this->touch();

        return PaymentResult::APPLIED;
    }

    public function getTotal(): int
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->getTotal();
        }

        return $total;
    }

    public function getCreditedAmount(): int
    {
        $credited = 0;
        foreach ($this->creditNotes as $creditNote) {
            $credited += $creditNote->getAmount();
        }

        return $credited;
    }

    public function getBalance(): int
    {
        return $this->getTotal() - $this->amountPaid - $this->getCreditedAmount();
    }

    public function isOverdue(?DateTimeImmutable $today = null): bool
    {
        if ($this->dueDate === null) {
            return false;
        }
        if (!\in_array($this->status, [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID], true)) {
            return false;
        }

        return $this->dueDate < ($today ?? new DateTimeImmutable('today'));
    }

    /** @return list<InvoiceDomainEvent> */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    protected function record(InvoiceDomainEvent $event): void
    {
        $this->events[] = $event;
    }

    protected function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    protected function assertEditable(): void
    {
        if ($this->status !== InvoiceStatus::DRAFT) {
            throw new InvoiceNotEditableException($this->status->value);
        }
    }
}
