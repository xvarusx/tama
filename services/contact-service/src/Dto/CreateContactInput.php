<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\ContactType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateContactInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
        public ContactType $contactType = ContactType::CLIENT,
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public ?string $email = null,
        #[Assert\Length(max: 255)]
        public ?string $phone = null,
        #[Assert\Length(max: 255)]
        public ?string $address = null,
        #[Assert\Length(max: 50)]
        public ?string $taxNumber = null,
    ) {
    }
}
