<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateSettingRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Length(max: 1000)]
        public string $value,
    ) {}
}
