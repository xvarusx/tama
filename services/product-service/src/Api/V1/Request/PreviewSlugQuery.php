<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PreviewSlugQuery
{
    public function __construct(
        #[Assert\Choice(choices: ['brand', 'category'])]
        public string $entity,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        /** Pass the id when editing, so the item does not clash with its own slug. */
        #[Assert\Positive]
        public ?int $ignoreId = null,
    ) {}
}
