<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Dto\InvoiceLineInput;
use App\Dto\UpdateInvoiceInput;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateInvoiceRequest
{
    /** @param InvoiceLineRequest[] $lines */
    public function __construct(
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\Valid]
        public array $lines,
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public ?string $partyName = null,
        #[Assert\Currency]
        public ?string $currency = null,
    ) {}

    public function toInput(): UpdateInvoiceInput
    {
        return new UpdateInvoiceInput(
            $this->partyName,
            $this->currency,
            array_map(
                static fn(InvoiceLineRequest $line): InvoiceLineInput => new InvoiceLineInput(
                    Uuid::fromString($line->productId),
                    $line->description,
                    $line->quantity,
                    $line->unitPrice,
                ),
                $this->lines,
            ),
        );
    }
}
