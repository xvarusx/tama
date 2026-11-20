<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A single reminder as returned by the API. */
final readonly class ReminderDocument
{
    public function __construct(
        public string $id,
        public ReminderAttributesDocument $attributes,
        /** Resource type, always "reminder". */
        public string $type = 'reminder',
    ) {}
}