<?php

declare(strict_types=1);

namespace App\Api\V1\Resource;

use JsonSerializable;

/** A contact as returned by the API. */
final readonly class ContactResource implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type,
        public array $attributes,
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
