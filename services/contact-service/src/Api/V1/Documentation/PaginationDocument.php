<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'PaginationDocument')]
final readonly class PaginationDocument
{
    public function __construct(
        #[OA\Property(type: 'integer', example: 1)]
        public int $currentPage,
        #[OA\Property(type: 'integer', example: 5)]
        public int $totalPages,
        #[OA\Property(type: 'integer', example: 20)]
        public int $pageSize,
        #[OA\Property(type: 'integer', example: 100)]
        public int $totalItems,
        #[OA\Property(type: 'object', properties: [
            new OA\Property(property: 'first', type: 'string', format: 'uri'),
            new OA\Property(property: 'prev', type: 'string', format: 'uri'),
            new OA\Property(property: 'next', type: 'string', format: 'uri'),
            new OA\Property(property: 'last', type: 'string', format: 'uri'),
        ])]
        public array $links,
    ) {}
}
