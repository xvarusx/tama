<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_lines')]
#[ORM\Index(name: 'idx_invoice_lines_invoice', columns: ['invoice_id'])]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity;

    /** minor units */
    #[ORM\Column]
    private int $unitPrice;

    /** basis points, e.g. 2000 = 20% */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $taxRateBps;

    /** minor units */
    #[ORM\Column]
    private int $taxAmount;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $productId,
        string $description,
        int $quantity,
        int $unitPrice,
        int $taxRateBps,
        int $taxAmount,
    ) {
        if (trim($description) === '') {
            throw new InvalidArgumentException('Line description must not be blank.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Line quantity must be positive.');
        }
        if ($unitPrice < 0) {
            throw new InvalidArgumentException('Line unit price must not be negative.');
        }
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->setTax($taxRateBps, $taxAmount);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function getProductId(): Uuid
    {
        return $this->productId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function getTaxRateBps(): int
    {
        return $this->taxRateBps;
    }

    public function getTaxAmount(): int
    {
        return $this->taxAmount;
    }

    public function setTax(int $taxRateBps, int $taxAmount): void
    {
        if ($taxRateBps < 0 || $taxRateBps > 10000) {
            throw new InvalidArgumentException('Tax rate must be between 0 and 10000 basis points.');
        }
        if ($taxAmount < 0) {
            throw new InvalidArgumentException('Tax amount must not be negative.');
        }

        $this->taxRateBps = $taxRateBps;
        $this->taxAmount = $taxAmount;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getSubtotal(): int
    {
        return $this->quantity * $this->unitPrice;
    }

    public function getTotal(): int
    {
        return $this->getSubtotal() + $this->taxAmount;
    }
}
