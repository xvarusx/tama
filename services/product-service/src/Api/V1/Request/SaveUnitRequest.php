<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class SaveUnitRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 55)]
        public readonly string $code,
        public readonly bool $allowDecimal = false,
    ) {}
}
