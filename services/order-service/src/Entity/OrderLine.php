<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_order_line_product', columns: ['product_id'])]
class OrderLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $productId;

    #[ORM\Column(length: 255)]
    private string $productName;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $quantityProcessed = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $unitPrice;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProductId(): Uuid
    {
        return $this->productId;
    }

    public function setProductId(Uuid $productId): self
    {
        $this->productId = $productId;
        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): self
    {
        $this->productName = $productName;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function changeQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getQuantityProcessed(): int
    {
        return $this->quantityProcessed;
    }

    public function getProcessedQuantity(): int
    {
        return $this->quantityProcessed;
    }

    public function process(int $quantity): self
    {
        $this->quantityProcessed += $quantity;
        return $this;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getSubtotal(): int
    {
        return $this->quantity * $this->unitPrice;
    }

    public function isFullyProcessed(): bool
    {
        return $this->quantityProcessed >= $this->quantity;
    }
}