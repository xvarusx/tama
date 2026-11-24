<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

#[ORM\Entity]
#[ORM\Table(name: 'credit_notes')]
#[ORM\UniqueConstraint(name: 'uniq_credit_notes_number', columns: ['number'])]
#[ORM\Index(name: 'idx_credit_notes_invoice', columns: ['invoice_id'])]
class CreditNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $number;

    /** minor units */
    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 255)]
    private string $reason;

    #[ORM\Column]
    private DateTimeImmutable $issuedAt;

    public function __construct(#[ORM\ManyToOne(inversedBy: 'creditNotes')]
        #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
        private Invoice $invoice, string $number, int $amount, string $reason)
    {
        if (trim($number) === '') {
            throw new InvalidArgumentException('Credit note number must not be blank.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit note amount must be positive.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Credit note reason must not be blank.');
        }
        $this->number = $number;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->issuedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getIssuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }
}
