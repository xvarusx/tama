<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvoiceAlreadyExistsForOrderException extends ApiException
{
    public function __construct(string $orderId)
    {
        parent::__construct(sprintf('Order "%s" already has an active invoice.', $orderId));
    }

    public function getErrorCode(): string
    {
        return 'INVOICE_ALREADY_EXISTS_FOR_ORDER';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
