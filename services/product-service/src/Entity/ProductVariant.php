<?php

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use App\Traits\ShareableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
class ProductVariant
{
    use ShareableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $sku = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $costPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $sellingPrice = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $stockUpdatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'productVariant')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    public function __construct(string $sku, string $costPrice, string $sellingPrice, ?string $barcode = null, ?array $options = null)
    {
        $this->sku = $sku;
        $this->costPrice = $costPrice;
        $this->sellingPrice = $sellingPrice;
        $this->barcode = $barcode;
        $this->options = $options;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getCostPrice(): ?string
    {
        return $this->costPrice;
    }

    public function setCostPrice(string $costPrice): static
    {
        $this->costPrice = $costPrice;

        return $this;
    }

    public function getSellingPrice(): ?string
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(string $sellingPrice): static
    {
        $this->sellingPrice = $sellingPrice;

        return $this;
    }

    public function getStockUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->stockUpdatedAt;
    }

    public function setStockUpdatedAt(?\DateTimeImmutable $stockUpdatedAt): static
    {
        $this->stockUpdatedAt = $stockUpdatedAt;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }
}
