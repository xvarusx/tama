<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Channel;
use App\Enum\DeliveryStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DeliveryFilter
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Positive]
        public int $pageSize = 20,
        public ?DeliveryStatus $status = null,
        public ?Channel $channel = null,
        public ?string $type = null,
        public ?string $search = null,
    ) {}
}
