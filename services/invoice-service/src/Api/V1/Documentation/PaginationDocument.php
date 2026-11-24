<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Pagination metadata returned with list endpoints. */
final readonly class PaginationDocument
{
    /** @param array<string, string> $links first / prev / next / last page URLs */
    public function __construct(
        public int $currentPage,
        public int $totalPages,
        public int $pageSize,
        public int $totalItems,
        public array $links,
    ) {}
}
