<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Channel;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_preferences')]
#[ORM\UniqueConstraint(name: 'uniq_notification_preferences', columns: ['user_id', 'type', 'channel'])]
class NotificationPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column]
        private int $userId,
        #[ORM\Column(length: 120)]
        private string $type,
        #[ORM\Column(length: 20, enumType: Channel::class)]
        private Channel $channel,
        #[ORM\Column]
        private bool $enabled
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
