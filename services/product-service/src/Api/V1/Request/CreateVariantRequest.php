<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateVariantRequest
{
    private const MONEY = '/^\d{1,10}(\.\d{1,2})?$/';

    /**
     * Prices are sent as strings ("29.99"), never floats, to avoid rounding errors.
     * SKU uniqueness needs the database: it is checked in the application layer.
     */
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
    ) {
    }
}
