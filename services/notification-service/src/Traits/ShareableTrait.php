<?php

declare(strict_types=1);

namespace App\Traits;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Use together with #[ORM\HasLifecycleCallbacks] on the entity.
 * Nobody sets these values by hand: there are no setters on purpose.
 */
trait ShareableTrait
{
    #[ORM\Column(nullable: false)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: false)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function init(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
