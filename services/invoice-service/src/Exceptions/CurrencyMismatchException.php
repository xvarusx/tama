<?php

declare(strict_types=1);

namespace App\Exceptions;

final class CurrencyMismatchException extends ApiException
{
    public function __construct(string $expected, string $given)
    {
        parent::__construct(sprintf('Payment currency "%s" does not match invoice currency "%s".', $given, $expected));
    }

    public function getErrorCode(): string
    {
        return 'CURRENCY_MISMATCH';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
