<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\ContactStatus;
use App\Enum\ContactType;
use Symfony\Component\Serializer\Attribute\MapQueryString;
use Symfony\Component\Validator\Constraints as Assert;

#[MapQueryString]
final readonly class ListContactsQuery
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $search = null,
        public ?ContactType $type = null,
        public ?ContactStatus $status = null,
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $pageSize = 20,
    ) {}
}
