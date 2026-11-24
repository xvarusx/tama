<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListPaymentPlansQuery
{
    public function __construct(
        public int $page = 1,
        
        public int $pageSize = 15,
        
        public ?string $invoiceId = null,
    ) {}
}