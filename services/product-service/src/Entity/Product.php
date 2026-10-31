<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[UniqueEntity(fields: ['sku'])]
class Product
{
    public const STATUS = ['active', 'archive'];
    #[Groups(['product:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['product:read'])]
    #[ORM\Column(length: 255)]
    private ?string $sku = null;

    #[Groups(['product:read'])]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[Groups(['product:read'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Groups(['product:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $brand = null;

    #[Groups(['product:read'])]
    #[ORM\Column(length: 255)]
    private ?string $unit = null;

    #[Groups(['product:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 0)]
    private ?string $sellingPrice = null;

    #[Groups(['product:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 0)]
    private ?string $costPrice = null;

    #[Groups(['product:read'])]
    #[Assert\Url(
        protocols: ['https', 'ftp'],
    )]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[Groups(['product:read'])]
    #[Assert\Choice(choices: Product::STATUS, message: 'status is not valid')]
    #[ORM\Column]
    private ?string $status = null;

    #[Groups(['product:read'])]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[Groups(['product:read'])]
    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[Groups(['product:read'])]
    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?Category $category = null;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = $unit;

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

    public function getCostPrice(): ?string
    {
        return $this->costPrice;
    }

    public function setCostPrice(string $costPrice): static
    {
        $this->costPrice = $costPrice;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }
}
