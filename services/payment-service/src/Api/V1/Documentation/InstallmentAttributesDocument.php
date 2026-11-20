<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** The attributes block of an installment resource. */
final readonly class InstallmentAttributesDocument
{
    public function __construct(
        public int $sequence,
        public string $dueDate,
        public int $amount,
        public int $paidAmount,
        public string $status,
        public int $remaining,
    ) {}
}