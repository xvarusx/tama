<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListInvoicesQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $pageSize = 20,
        public ?InvoiceStatus $status = null,
        public ?InvoiceType $type = null,
        #[Assert\Uuid]
        public ?string $orderId = null,
        #[Assert\Uuid]
        public ?string $partyId = null,
        #[Assert\Length(max: 100)]
        public ?string $search = null,
        public ?bool $overdue = null,
    ) {}
}
