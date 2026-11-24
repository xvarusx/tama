<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** One invoice as returned inside the success envelope. */
final readonly class InvoiceDocument
{
    public function __construct(
        public string $id,
        public InvoiceAttributesDocument $attributes,
        /** Always "invoice". */
        public string $type = 'invoice',
    ) {}
}
