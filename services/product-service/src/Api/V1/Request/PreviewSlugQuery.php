<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class PreviewSlugQuery
{
    public function __construct(
        #[Assert\Choice(choices: ['brand', 'category'])]
        public readonly string $entity,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,

        /** Pass the id when editing, so the item does not clash with its own slug. */
        #[Assert\Positive]
        public readonly ?int $ignoreId = null,
    ) {}
}
