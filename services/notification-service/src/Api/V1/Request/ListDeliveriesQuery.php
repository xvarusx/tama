<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\Channel;
use App\Enum\DeliveryStatus;
use Symfony\Component\Validator\Constraints as Assert;

/** Use with #[MapQueryString] on the admin delivery list. */
final readonly class ListDeliveriesQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $pageSize = 20,
        public ?DeliveryStatus $status = null,
        public ?Channel $channel = null,
        #[Assert\Length(max: 120)]
        public ?string $type = null,
        #[Assert\Length(max: 100)]
        public ?string $search = null,
    ) {}
}
