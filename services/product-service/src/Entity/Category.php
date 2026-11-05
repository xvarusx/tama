<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use App\Traits\ShareableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    use ShareableTrait;
    #[Groups(['product:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'category')]
    private Collection $products;

    public function __construct(?string $name = null, ?string $slug = null, int $position = 0)
    {
        $this->products = new ArrayCollection();
        if ($name !== null) {
            $this->name = $name;
        }
        if ($slug !== null) {
            $this->slug = $slug;
        }
        $this->position = $position;
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

    public function rename(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function changeSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function moveTo(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
