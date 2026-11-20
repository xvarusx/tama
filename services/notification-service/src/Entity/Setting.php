<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'settings')]
class Setting
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(
        /** "key" is a reserved word on MySQL, so the column is named setting_key; the property stays key. */
        #[ORM\Column(name: 'setting_key', length: 190, unique: true)]
        private string $key,
        #[ORM\Column(type: Types::TEXT)]
        private string $value,
        #[ORM\Column(length: 255)]
        private string $description
    ) {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}
