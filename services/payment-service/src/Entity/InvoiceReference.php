<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Read model that represents a denormalized view of invoice data.
 * Updated via events from the Invoice Service.
 */
#[ORM\Entity(repositoryClass: 'App\Repository\InvoiceReferenceRepository')]
#[ORM\Index(name: 'idx_invoice_ref_number', columns: ['number'])]
#[ORM\Index(name: 'idx_invoice_ref_party', columns: ['party_id'])]
#[ORM\Index(name: 'idx_invoice_ref_status', columns: ['status'])]
class InvoiceReference
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $invoiceId;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    private string $number;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $partyId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $partyName;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::INTEGER)]
    private int $total;

    #[ORM\Column(type: Types::INTEGER)]
    private int $outstanding;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $invoiceId,
        string $number,
        string $type,
        string $partyId,
        string $partyName,
        string $currency,
        int $total,
        int $outstanding,
        string $status,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = Uuid::v7();
        $this->invoiceId = $invoiceId;
        $this->number = $number;
        $this->type = $type;
        $this->partyId = $partyId;
        $this->partyName = $partyName;
        $this->currency = $currency;
        $this->total = $total;
        $this->outstanding = $outstanding;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
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

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(string $invoiceId): self
    {
        $this->invoiceId = $invoiceId;
        return $this;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getPartyId(): string
    {
        return $this->partyId;
    }

    public function setPartyId(string $partyId): self
    {
        $this->partyId = $partyId;
        return $this;
    }

    public function getPartyName(): string
    {
        return $this->partyName;
    }

    public function setPartyName(string $partyName): self
    {
        $this->partyName = $partyName;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getOutstanding(): int
    {
        return $this->outstanding;
    }

    public function setOutstanding(int $outstanding): self
    {
        $this->outstanding = $outstanding;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Update the read model from an invoice event.
     */
    public function refreshFrom(array $eventData): self
    {
        $this->invoiceId = $eventData['invoiceId'];
        $this->number = $eventData['number'];
        $this->type = $eventData['type'];
        $this->partyId = $eventData['partyId'];
        $this->partyName = $eventData['partyName'];
        $this->currency = $eventData['currency'];
        $this->total = $eventData['total'];
        $this->outstanding = $eventData['outstanding'];
        $this->status = $eventData['status'];
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }
}