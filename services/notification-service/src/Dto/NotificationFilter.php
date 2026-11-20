<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\NotificationStatus;
use App\Enum\Severity;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class NotificationFilter
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Positive]
        public int $pageSize = 20,
        public ?NotificationStatus $status = null,
        public ?string $type = null,
        public ?Severity $severity = null,
        public ?string $search = null,
    ) {}
}
