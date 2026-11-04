<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Use with #[MapQueryString]. Deleted products are never listed. */
final class ListProductsQuery
{
    public function __construct(
        #[Assert\Positive]
        public readonly int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public readonly int $pageSize = 20,
        #[Assert\Length(max: 100)]
        public readonly ?string $search = null,
        #[Assert\Positive]
        public readonly ?int $categoryId = null,
        #[Assert\Positive]
        public readonly ?int $brandId = null,
    ) {
    }
}
