<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\SettingRepository')]
#[ORM\Index(name: 'idx_setting_key', columns: ['setting_key'])]
#[ORM\Index(name: 'idx_setting_type', columns: ['type'])]
class Setting
{
    /**
     * "key" is a reserved word in MySQL, so it is stored as setting_key while
     * the PHP property keeps its natural name.
     */
    #[ORM\Id]
    #[ORM\Column(name: 'setting_key', type: Types::STRING, length: 255, unique: true)]
    private string $key;

    #[ORM\Column(type: Types::TEXT)]
    private string $value;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $type;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __construct(string $key, string $value, string $type, ?string $description = null)
    {
        $this->key = $key;
        $this->value = $value;
        $this->type = $type;
        $this->description = $description;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function asInt(): int
    {
        return (int) $this->value;
    }

    public function asBool(): bool
    {
        return (bool) filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }
}