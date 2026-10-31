<?php

namespace App\Message;

final class ProductUpdated
{
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public string $sellingPrice,
        public string $unit,
        public string $status = 'active',
    ) {
    }
}
