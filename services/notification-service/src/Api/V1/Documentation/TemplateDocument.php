<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Documented notification template. */
final readonly class TemplateDocument
{
    public function __construct(
        public string $id,
        public string $type,
        public string $channel,
        public string $locale,
        public string $subject,
        public string $bodyTemplate,
    ) {}
}
