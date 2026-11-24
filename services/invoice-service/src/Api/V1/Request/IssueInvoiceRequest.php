<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssueInvoiceRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\DateTime(format: 'Y-m-d', message: 'The due date must be a valid Y-m-d date.')]
        public ?string $dueDate = null,
    ) {}

    public function toDueDate(): ?DateTimeImmutable
    {
        if ($this->dueDate === null) {
            return null;
        }

        $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $this->dueDate);
        if ($dueDate === false) {
            return null;
        }

        return $dueDate;
    }
}
