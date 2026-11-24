<?php

declare(strict_types=1);

namespace App\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case VOIDED = 'voided';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => \in_array($target, [self::ISSUED, self::VOIDED], true),
            self::ISSUED => \in_array($target, [self::PARTIALLY_PAID, self::PAID, self::VOIDED], true),
            self::PARTIALLY_PAID => $target === self::PAID,
            self::PAID, self::VOIDED => false,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::PAID || $this === self::VOIDED;
    }
}
