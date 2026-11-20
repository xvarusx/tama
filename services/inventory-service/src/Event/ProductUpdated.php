<?php

declare(strict_types=1);

namespace App\Event;

final class ProductUpdated
{
    public function __construct(
        public string $productId,
        public string $sku,
        public string $name,
        public string $sellingPrice,
        public string $unit,
        public string $status = 'active',
    ) {}
}
