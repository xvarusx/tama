<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** A single installment as returned by the API. */
final readonly class InstallmentDocument
{
    public function __construct(
        public string $id,
        public InstallmentAttributesDocument $attributes,
        /** Resource type, always "installment". */
        public string $type = 'installment',
    ) {}
}