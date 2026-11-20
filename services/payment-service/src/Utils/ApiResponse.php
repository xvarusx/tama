<?php

declare(strict_types=1);

namespace App\Utils;

use JsonSerializable;

final readonly class ApiResponse implements JsonSerializable
{
    private function __construct(
        private mixed $data,
        private ?Pagination $pagination,
        private ?string $code = null,
        private ?string $message = null,
    ) {}

    public static function success(mixed $data, ?Pagination $pagination = null): self
    {
        return new self($data, $pagination);
    }

    public static function error(string $code, string $message): self
    {
        return new self(null, null, $code, $message);
    }

    public function jsonSerialize(): array
    {
        if ($this->code !== null && $this->message !== null) {
            return [
                'status' => 'error',
                'error' => [
                    'code' => $this->code,
                    'message' => $this->message,
                ],
            ];
        }
        
        $body = ['status' => 'success', 'data' => $this->data];
        if ($this->pagination !== null) {
            $body['pagination'] = $this->pagination;
        }

        return $body;
    }
}