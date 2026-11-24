<?php

declare(strict_types=1);

namespace App\Api\V1\Request;

use App\Dto\InvoiceDraftInput;
use App\Dto\InvoiceLineInput;
use App\Enum\InvoiceType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInvoiceRequest
{
    /**
     * Tax rate and tax amount are never accepted from the caller; they come
     * from the Tax Service through the create handler.
     *
     * @param InvoiceLineRequest[] $lines
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $orderId,
        #[Assert\NotNull]
        public InvoiceType $type,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $partyId,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $partyName,
        #[Assert\NotBlank]
        #[Assert\Currency]
        public string $currency,
        #[Assert\Count(min: 1, max: 100)]
        #[Assert\Valid]
        public array $lines,
    ) {}

    public function toInput(): InvoiceDraftInput
    {
        return new InvoiceDraftInput(
            Uuid::fromString($this->orderId),
            $this->type,
            Uuid::fromString($this->partyId),
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
