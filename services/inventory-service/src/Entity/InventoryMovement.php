<?php

namespace App\Entity;

use App\Enum\InventoryMovementType;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class InventoryMovement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['inventory:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'movements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Inventory $inventory = null;

    #[ORM\Column(type: 'uuid')]
    #[Groups(['inventory:read'])]
    private ?Uuid $productId = null;

    #[ORM\Column(length: 32, enumType: InventoryMovementType::class)]
    #[Groups(['inventory:read'])]
    private ?InventoryMovementType $type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    #[Groups(['inventory:read'])]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    #[Groups(['inventory:read'])]
    private ?string $quantityAfter = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    #[Groups(['inventory:read'])]
    private ?string $quantityDelta = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    #[Groups(['inventory:read'])]
    private ?string $balanceAfter = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    #[Groups(['inventory:read'])]
    private ?string $reservedDelta = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    #[Groups(['inventory:read'])]
    private ?string $reservedAfter = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Groups(['inventory:read'])]
    private ?string $referenceType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['inventory:read'])]
    private ?string $referenceId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['inventory:read'])]
    private ?string $reason = null;

    #[ORM\Column]
    #[Groups(['inventory:read'])]
    private ?DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(?Inventory $inventory): static
    {
        $this->inventory = $inventory;
        $this->productId = $inventory?->getProductId();

        return $this;
    }

    public function getProductId(): ?Uuid
    {
        return $this->productId;
    }

    public function getType(): ?InventoryMovementType
    {
        return $this->type;
    }

    public function setType(InventoryMovementType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantityAfter(): ?string
    {
        return $this->quantityAfter;
    }

    public function setQuantityAfter(?string $quantityAfter): static
    {
        $this->quantityAfter = $quantityAfter;

        return $this;
    }

    public function getQuantityDelta(): ?string
    {
        return $this->quantityDelta;
    }

    public function setQuantityDelta(?string $quantityDelta): static
    {
        $this->quantityDelta = $quantityDelta;

        return $this;
    }

    public function getBalanceAfter(): ?string
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(?string $balanceAfter): static
    {
        $this->balanceAfter = $balanceAfter;

        return $this;
    }

    public function getReservedDelta(): ?string
    {
        return $this->reservedDelta;
    }

    public function setReservedDelta(?string $reservedDelta): static
    {
        $this->reservedDelta = $reservedDelta;

        return $this;
    }

    public function getReservedAfter(): ?string
    {
        return $this->reservedAfter;
    }

    public function setReservedAfter(?string $reservedAfter): static
    {
        $this->reservedAfter = $reservedAfter;

        return $this;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function setReferenceType(?string $referenceType): static
    {
        $this->referenceType = $referenceType;

        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): static
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isIncoming(): bool
    {
        return $this->type === InventoryMovementType::IN;
    }

    public function isOutgoing(): bool
    {
        return $this->type === InventoryMovementType::OUT;
    }
}
