<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidInvoiceTransitionException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'INVALID_INVOICE_TRANSITION';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
