<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** e.g. /skus/preview?brandId=0199f4c0-4d21-7c11-8f2a-1b2c3d4e5f60&name=Wireless Mouse&options[size]=M */
final readonly class PreviewSkuQuery
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $brandId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
        public ?array $options = null,
    ) {}
}
