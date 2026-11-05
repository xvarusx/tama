<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** e.g. /skus/preview?brandId=1&name=Wireless Mouse&options[size]=M&options[color]=Black */
final readonly class PreviewSkuQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $brandId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
        public ?array $options = null,
    ) {}
}
