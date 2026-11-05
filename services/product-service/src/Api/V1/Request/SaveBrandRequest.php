<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Used for create (POST) and update (PUT). The slug is generated from the name when omitted. */
final readonly class SaveBrandRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
        #[Assert\Length(max: 255)]
        #[Assert\Regex('/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Use lowercase letters, digits and dashes only.')]
        public ?string $slug = null,
    ) {}
}
