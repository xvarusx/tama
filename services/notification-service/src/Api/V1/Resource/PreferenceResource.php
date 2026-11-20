<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

/** Effective channel preferences for one notification type. */
final readonly class PreferenceResource
{
    /** @param array{in_app: bool, email: bool} $channels */
    public function __construct(
        public string $type,
        public array $channels,
    ) {}
}
