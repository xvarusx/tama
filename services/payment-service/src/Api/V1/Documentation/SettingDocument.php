<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A single setting as returned by the API. */
final readonly class SettingDocument
{
    public function __construct(
        public string $key,
        public SettingAttributesDocument $attributes,
    ) {}
}