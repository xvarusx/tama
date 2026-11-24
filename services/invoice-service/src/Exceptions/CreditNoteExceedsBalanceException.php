<?php

declare(strict_types=1);

namespace App\Exceptions;

final class CreditNoteExceedsBalanceException extends ApiException
{
    public function __construct(int $amount, int $remaining)
    {
        parent::__construct(sprintf(
            'Credit note amount %d exceeds the remaining invoice value %d.',
            $amount,
            $remaining,
        ));
    }

    public function getErrorCode(): string
    {
        return 'CREDIT_NOTE_EXCEEDS_BALANCE';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
