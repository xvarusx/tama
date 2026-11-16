<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Channel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'notification_templates')]
#[ORM\UniqueConstraint(name: 'uniq_notification_templates', columns: ['type', 'channel', 'locale'])]
class NotificationTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(#[ORM\Column(length: 120)]
        private string $type, #[ORM\Column(length: 20, enumType: Channel::class)]
        private Channel $channel, #[ORM\Column(length: 5)]
        private string $locale, #[ORM\Column(length: 255)]
        private string $subject, #[ORM\Column(type: Types::TEXT)]
        private string $bodyTemplate)
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBodyTemplate(): string
    {
        return $this->bodyTemplate;
    }

    public function update(string $subject, string $bodyTemplate): void
    {
        $this->subject = $subject;
        $this->bodyTemplate = $bodyTemplate;
    }
}
