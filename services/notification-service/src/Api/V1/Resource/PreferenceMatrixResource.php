<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

/** Full preference matrix returned by GET and PUT /preferences. */
final readonly class PreferenceMatrixResource
{
    /** @param list<PreferenceResource> $preferences */
    public function __construct(
        public array $preferences,
    ) {}
}
