<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvoiceNotEditableException extends ApiException
{
    public function __construct(string $status)
    {
        parent::__construct(sprintf('An invoice with status "%s" is not editable.', $status));
    }

    public function getErrorCode(): string
    {
        return 'INVOICE_NOT_EDITABLE';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
