<?php

declare(strict_types=1);

namespace App\Utils;

use JsonSerializable;

final readonly class ApiResponse implements JsonSerializable
{
    private function __construct(
        private mixed $data,
        private ?Pagination $pagination,
        private ?array $meta,
    ) {}

    public static function success(mixed $data, ?Pagination $pagination = null, ?array $meta = null): self
    {
        return new self($data, $pagination, $meta);
    }

    public function jsonSerialize(): array
    {
        $body = ['status' => 'success', 'data' => $this->data];
        if ($this->pagination !== null) {
            $body['pagination'] = $this->pagination;
        }
        if ($this->meta !== null) {
            $body['meta'] = $this->meta;
        }

        return $body;
    }
}
