<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class SaveCategoryRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        #[Assert\Length(max: 255)]
        #[Assert\Regex('/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Use lowercase letters, digits and dashes only.')]
        public readonly ?string $slug = null,
        #[Assert\PositiveOrZero]
        public readonly int $position = 0,
    ) {}
}
