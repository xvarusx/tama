<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\ContactStatus;
use App\Enum\ContactType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateContactRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[Groups(['update'])]
        public ?string $name = null,
        #[Groups(['update'])]
        public ?ContactType $type = null,
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        #[Groups(['update'])]
        public ?string $email = null,
        #[Assert\Length(max: 255)]
        #[Groups(['update'])]
        public ?string $phone = null,
        #[Assert\Length(max: 255)]
        #[Groups(['update'])]
        public ?string $address = null,
        #[Assert\Length(max: 50)]
        #[Groups(['update'])]
        public ?string $taxNumber = null,
        #[Groups(['update'])]
        public ?ContactStatus $status = null,
    ) {}
}
