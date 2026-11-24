<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

use JsonSerializable;

final readonly class ContactStatusResource implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type = 'contact-status',
        public array $attributes = [],
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'attributes' => $this->attributes,
        ];
    }
}
