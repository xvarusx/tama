<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\NotificationStatus;
use App\Enum\Severity;
use Symfony\Component\Validator\Constraints as Assert;

/** Use with #[MapQueryString] on the inbox list endpoint. */
final readonly class ListNotificationsQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $pageSize = 20,
        public ?NotificationStatus $status = null,
        public ?string $type = null,
        public ?Severity $severity = null,
        #[Assert\Length(max: 100)]
        public ?string $search = null,
    ) {}
}
