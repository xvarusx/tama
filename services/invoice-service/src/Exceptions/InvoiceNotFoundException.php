<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvoiceNotFoundException extends ApiException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Invoice "%s" does not exist.', $id));
    }

    public function getErrorCode(): string
    {
        return 'INVOICE_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }
}
