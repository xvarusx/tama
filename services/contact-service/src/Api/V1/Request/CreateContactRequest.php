<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Enum\ContactType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateContactRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[Groups(['create'])]
        public string $name,
        #[Assert\NotBlank]
        #[Groups(['create'])]
        public ContactType $type,
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        #[Groups(['create'])]
        public ?string $email = null,
        #[Assert\Length(max: 255)]
        #[Groups(['create'])]
        public ?string $phone = null,
        #[Assert\Length(max: 255)]
        #[Groups(['create'])]
        public ?string $address = null,
        #[Assert\Length(max: 50)]
        #[Groups(['create'])]
        public ?string $taxNumber = null,
    ) {}
}
