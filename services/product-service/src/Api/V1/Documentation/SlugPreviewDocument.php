<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The slug that would be generated for the given name. */
final readonly class SlugPreviewDocument
{
    public function __construct(
        public string $slug,
    ) {}
}
