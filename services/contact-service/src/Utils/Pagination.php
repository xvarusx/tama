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
        $totalPages = (int) ceil($totalItems / max(1, $pageSize));
        $currentPage = min(max(1, $page), max(1, $totalPages));

        $links = [
            'first' => $urlForPage(1),
            'last' => $urlForPage($totalPages),
        ];
        if ($currentPage > 1) {
            $links['prev'] = $urlForPage($currentPage - 1);
        }
        if ($currentPage < $totalPages) {
            $links['next'] = $urlForPage($currentPage + 1);
        }

        return new self($currentPage, $totalPages, $pageSize, $totalItems, $links);
    }
}
