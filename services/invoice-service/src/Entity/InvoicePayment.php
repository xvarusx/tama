<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_payments')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_payments_payment_id', columns: ['payment_id'])]
#[ORM\Index(name: 'idx_invoice_payments_invoice', columns: ['invoice_id'])]
class InvoicePayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** minor units */
    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column]
    private DateTimeImmutable $receivedAt;

    public function __construct(#[ORM\ManyToOne(inversedBy: 'payments')]
        #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
        private Invoice $invoice, /** Unique across every invoice; the idempotency key. */
        #[ORM\Column(type: 'uuid')]
        private Uuid $paymentId, int $amount, string $currency, /** false when the payment exceeded the remaining balance and was flagged instead of applied */
        #[ORM\Column]
        private bool $accepted)
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive.');
        }
        if (\strlen($currency) !== 3) {
            throw new InvalidArgumentException('Payment currency must be a 3-letter ISO code.');
        }
        $this->amount = $amount;
        $this->currency = $currency;
        $this->receivedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getPaymentId(): Uuid
    {
        return $this->paymentId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function getReceivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
