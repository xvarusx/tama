<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\InvoiceType;
use Symfony\Component\Uid\Uuid;

final readonly class InvoiceDraftInput
{
    /** @param list<InvoiceLineInput> $lines */
    public function __construct(
        public Uuid $orderId,
        public InvoiceType $type,
        public Uuid $partyId,
        public string $partyName,
        public string $currency,
        public array $lines,
    ) {}
}
