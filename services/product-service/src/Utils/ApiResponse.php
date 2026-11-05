<?php

declare(strict_types=1);

namespace App\Utils;

final class ApiResponse implements \JsonSerializable
{
    private function __construct(
        private readonly mixed $data,
        private readonly ?Pagination $pagination,
    ) {}

    public static function success(mixed $data, ?Pagination $pagination = null): self
    {
        return new self($data, $pagination);
    }

    public function jsonSerialize(): array
    {
        $body = ['status' => 'success', 'data' => $this->data];
        if ($this->pagination !== null) {
            $body['pagination'] = $this->pagination;
        }

        return $body;
    }
}
