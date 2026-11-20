<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateSettingRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $key,
        
        #[Assert\NotBlank]
        public string $value,
        
        #[Assert\Length(max: 255)]
        public ?string $description = null,
    ) {}
}