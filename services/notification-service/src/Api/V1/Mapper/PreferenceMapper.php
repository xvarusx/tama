<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\PreferenceResource;
use App\Enum\Channel;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationTemplateRepository;

/** Builds the type x channel matrix with effective (default enabled) values. */
final readonly class PreferenceMapper
{
    public function __construct(
        private NotificationTemplateRepository $templates,
        private NotificationPreferenceRepository $preferences,
    ) {}

    /**
     * One row per template type, with both channels defaulting to enabled.
     *
     * @return list<PreferenceResource>
     */
    public function matrix(string $userId): array
    {
        /** @var array<string, bool> $overrides */
        $overrides = [];
        foreach ($this->preferences->findBy(['userId' => $userId]) as $preference) {
            $overrides[$this->key($preference->getType(), $preference->getChannel())] = $preference->isEnabled();
        }

        $matrix = [];
        foreach ($this->templates->findDistinctTypes() as $type) {
            $matrix[] = new PreferenceResource(
                type: $type,
                channels: [
                    Channel::IN_APP->value => $overrides[$this->key($type, Channel::IN_APP)] ?? true,
                    Channel::EMAIL->value => $overrides[$this->key($type, Channel::EMAIL)] ?? true,
                ],
            );
        }

        return $matrix;
    }

    private function key(string $type, Channel $channel): string
    {
        return $type . '|' . $channel->value;
    }
}
