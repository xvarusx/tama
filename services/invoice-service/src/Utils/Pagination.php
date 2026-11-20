<?php

declare(strict_types=1);

namespace App\Utils;

use Closure;

final readonly class Pagination
{
    /** @param array<string, string> $links first / prev / next / last */
    public function __construct(
        public int $currentPage,
        public int $totalPages,
        public int $pageSize,
        public int $totalItems,
        public array $links,
    ) {}

    /** @param Closure(int): string $urlForPage builds the URL of a given page */
    public static function create(int $page, int $pageSize, int $totalItems, Closure $urlForPage): self
    {
        $totalPages = max(1, (int)ceil($totalItems / $pageSize));

        $links = ['first' => $urlForPage(1), 'last' => $urlForPage($totalPages)];
        if ($page > 1) {
            $links['prev'] = $urlForPage($page - 1);
        }
        if ($page < $totalPages) {
            $links['next'] = $urlForPage($page + 1);
        }

        return new self($page, $totalPages, $pageSize, $totalItems, $links);
    }
}
