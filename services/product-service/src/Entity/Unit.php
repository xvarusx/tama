<?php

namespace App\Entity;

use App\Repository\UnitRepository;
use App\Traits\ShareableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: UnitRepository::class)]
class Unit
{
    use ShareableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 55, unique: true)]
    private ?string $code = null;

    #[ORM\Column]
    private ?bool $allowDecimal = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'unit')]
    private Collection $products;

    public function __construct(?string $code = null, ?bool $allowDecimal = null)
    {
        $this->products = new ArrayCollection();
        if ($code !== null) {
            $this->code = $code;
        }
        if ($allowDecimal !== null) {
            $this->allowDecimal = $allowDecimal;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function changeCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function isAllowDecimal(): ?bool
    {
        return $this->allowDecimal;
    }

    public function setAllowDecimal(bool $allowDecimal): static
    {
        $this->allowDecimal = $allowDecimal;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setUnit($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getUnit() === $this) {
                $product->setUnit(null);
            }
        }

        return $this;
    }
}
