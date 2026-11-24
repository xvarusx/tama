<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_attempts')]
#[ORM\Index(name: 'idx_delivery_attempts_delivery', columns: ['delivery_id'])]
class DeliveryAttempt
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column]
    private DateTimeImmutable $attemptedAt;

    public function __construct(#[ORM\ManyToOne(inversedBy: 'deliveryAttempts')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Delivery $delivery, #[ORM\Column]
        private int $attemptNumber, #[ORM\Column(length: 255, nullable: true)]
        private ?string $providerMessageId, #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $error)
    {
        $this->id = Uuid::v7();
        $this->attemptedAt = new DateTimeImmutable();

        $this->delivery->addDeliveryAttempt($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDelivery(): Delivery
    {
        return $this->delivery;
    }

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getAttemptedAt(): DateTimeImmutable
    {
        return $this->attemptedAt;
    }
}
