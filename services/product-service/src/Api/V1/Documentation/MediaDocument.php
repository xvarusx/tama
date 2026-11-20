<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** An image attached to a product. */
final readonly class MediaDocument
{
    public function __construct(
        public string $id,
        /** Relative URL of the image, e.g. "/media/0199f4c0-4d21-7c11-8f2a-1b2c3d4e5f60". */
        public string $url,
        /** MIME type of the stored file. */
        public ?string $kind,
        public ?int $position,
    ) {}
}
