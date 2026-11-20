<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\InstallmentStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListInstallmentsQuery
{
    public function __construct(
        public int $page = 1,
        
        public int $pageSize = 15,
        
        public ?InstallmentStatus $status = null,
        
        public ?string $paymentPlanId = null,
        
        public ?bool $overdueOnly = false,
    ) {}
}