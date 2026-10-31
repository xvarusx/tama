<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class ProductUpdated
{
    /*
     * Add whatever properties and methods you need
     * to hold the data for this message class.
     */

    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public string $sellingPrice,
        public string $unit,
        public string $status,
    ) {}
}
