<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** An image attached to a product. */
final readonly class MediaDocument
{
    public function __construct(
        public int $id,
        /** Relative URL of the image, e.g. "/media/12". */
        public string $url,
        /** MIME type of the stored file. */
        public ?string $kind,
        public ?int $position,
    ) {}
}
