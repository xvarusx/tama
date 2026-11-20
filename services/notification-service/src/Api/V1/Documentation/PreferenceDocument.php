<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Preference matrix envelope payload. */
final readonly class PreferenceDocument
{
    /** @param list<PreferenceEntryDocument> $preferences */
    public function __construct(
        public array $preferences,
    ) {}
}
