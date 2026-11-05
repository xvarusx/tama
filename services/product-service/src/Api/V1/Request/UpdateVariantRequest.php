<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A variant as sent inside PUT /products/{id}. An id means "update this existing
 * variant"; a missing id means "create a new variant". Variants omitted from the
 * payload are deleted.
 */
final class UpdateVariantRequest
{
    private const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $sku,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::MONEY, message: 'Must be a decimal number with at most 2 decimals.')]
        public readonly string $costPrice,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::MONEY, message: 'Must be a decimal number with at most 2 decimals.')]
        public readonly string $sellingPrice,
        #[Assert\Length(max: 255)]
        public readonly ?string $barcode = null,

        /** e.g. {"size": "M", "color": "red"} */
        public readonly ?array $options = null,
        #[Assert\Positive]
        public readonly ?int $id = null,
    ) {}
}
