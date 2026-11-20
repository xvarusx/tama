<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PaymentIdConflictException extends ApiException
{
    public function __construct(string $paymentId)
    {
        parent::__construct(sprintf('Payment "%s" is already recorded on another invoice.', $paymentId));
    }

    public function getErrorCode(): string
    {
        return 'PAYMENT_ID_CONFLICT';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
