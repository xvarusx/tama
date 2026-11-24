<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Settings only. Quantities and productId are deliberately not updatable.
 */
final readonly class UpdateInventoryRequest
{
    public function __construct(
        #[Assert\Type('numeric')]
        #[Assert\PositiveOrZero]
        #[Assert\LessThanOrEqual('999999999.999')]
        public float|string $reorderLevel,
        #[Assert\Positive]
        public int $version,
    ) {}
}
