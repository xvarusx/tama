<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** One type with its in_app / email effective switches. */
final readonly class PreferenceEntryDocument
{
    /** @param array<string, bool> $channels */
    public function __construct(
        public string $type,
        public array $channels,
    ) {}
}
