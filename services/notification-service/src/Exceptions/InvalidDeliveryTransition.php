<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enum\DeliveryStatus;

final class InvalidDeliveryTransition extends ApiException
{
    public function __construct(DeliveryStatus $status, string $action)
    {
        parent::__construct(sprintf('Cannot perform "%s" on a delivery in status "%s".', $action, $status->value));
    }

    public function getErrorCode(): string
    {
        return 'INVALID_DELIVERY_TRANSITION';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
