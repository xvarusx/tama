<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The attributes block of a setting resource. */
final readonly class SettingAttributesDocument
{
    public function __construct(
        public string $value,
        public string $type,
        public ?string $description,
    ) {}
}