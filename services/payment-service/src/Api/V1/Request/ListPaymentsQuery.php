<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListPaymentsQuery
{
    public function __construct(
        public int $page = 1,
        
        public int $pageSize = 15,
        
        public ?PaymentStatus $status = null,
        
        public ?PaymentMethod $method = null,
        
        public ?string $partyId = null,
        
        public ?string $search = null,
    ) {}
}