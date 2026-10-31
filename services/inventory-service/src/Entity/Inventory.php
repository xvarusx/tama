<?php

namespace App\Entity;

use App\Repository\InventoryRepository;
use App\Util\Decimal;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['inventory:read'])]
    private ?int $id = null;


    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    #[Groups(['inventory:read'])]
    private ?string $quantityOnHand = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    #[Groups(['inventory:read'])]
    private ?string $quantityReserved = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    #[Groups(['inventory:read'])]
    private ?string $reorderLevel = '0.000';

    #[ORM\Column]
    #[Groups(['inventory:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['inventory:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['inventory:read'])]
    private int $version = 1;

    #[ORM\Column]
    #[Groups(['inventory:read'])]
    private ?int $productId = null;

    /** @var Collection<int, InventoryMovement> */
    #[ORM\OneToMany(mappedBy: 'inventory', targetEntity: InventoryMovement::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $movements;

    public function __construct()
    {
        $this->movements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantityOnHand(): ?string
    {
        return $this->quantityOnHand;
    }

    public function setQuantityOnHand(string $quantityOnHand): static
    {
        $this->quantityOnHand = $quantityOnHand;

        return $this;
    }

    public function getQuantityReserved(): ?string
    {
        return $this->quantityReserved;
    }

    public function setQuantityReserved(string $quantityReserved): static
    {
        $this->quantityReserved = $quantityReserved;

        return $this;
    }

    public function getReorderLevel(): ?string
    {
        return $this->reorderLevel;
    }

    public function setReorderLevel(string $reorderLevel): static
    {
        $this->reorderLevel = $reorderLevel;

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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    #[Groups(['inventory:read'])]
    public function getAvailableQuantity(): string
    {
        return Decimal::subtract($this->quantityOnHand ?? '0.000', $this->quantityReserved ?? '0.000');
    }

    #[Groups(['inventory:read'])]
    public function isLowStock(): bool
    {
        return Decimal::compare($this->getAvailableQuantity(), $this->reorderLevel ?? '0.000') <= 0;
    }

    /**
     * @return Collection<int, InventoryMovement>
     */
    public function getMovements(): Collection
    {
        return $this->movements;
    }

    public function addMovement(InventoryMovement $movement): static
    {
        if (!$this->movements->contains($movement)) {
            $this->movements->add($movement);
            $movement->setInventory($this);
        }

        return $this;
    }

    public function removeMovement(InventoryMovement $movement): static
    {
        if ($this->movements->removeElement($movement) && $movement->getInventory() === $this) {
            $movement->setInventory(null);
        }

        return $this;
    }

}
