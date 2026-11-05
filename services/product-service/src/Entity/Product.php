<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use App\Traits\ShareableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    use ShareableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    /** @var Collection<int, ProductMedia> */
    #[ORM\OneToMany(targetEntity: ProductMedia::class, mappedBy: 'product', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $media;

    /**
     * @var Collection<int, ProductVariant>
     */
    #[ORM\OneToMany(targetEntity: ProductVariant::class, mappedBy: 'product', orphanRemoval: true, cascade: ['persist'])]
    private Collection $productVariant;

    public function __construct(#[ORM\Column(length: 255)]
        private ?string $name = null, #[ORM\ManyToOne(inversedBy: 'products')]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Brand $brand = null, #[ORM\ManyToOne(inversedBy: 'products')]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Unit $unit = null)
    {
        $this->productVariant = new ArrayCollection();
        $this->media = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getProductVariant(): Collection
    {
        return $this->productVariant;
    }

    public function addProductVariant(ProductVariant $productVariant): static
    {
        if (!$this->productVariant->contains($productVariant)) {
            $this->productVariant->add($productVariant);
            $productVariant->setProduct($this);
        }

        return $this;
    }

    public function removeProductVariant(ProductVariant $productVariant): static
    {
        if ($this->productVariant->removeElement($productVariant)) {
            // set the owning side to null (unless already changed)
            if ($productVariant->getProduct() === $this) {
                $productVariant->setProduct(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, ProductMedia> */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(ProductMedia $media): static
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setProduct($this);
        }

        return $this;
    }

    public function removeMedia(ProductMedia $media): static
    {
        $this->media->removeElement($media);

        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): static
    {
        $this->brand = $brand;

        return $this;
    }
}
