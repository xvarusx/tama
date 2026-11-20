<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\Uid\Uuid;

final class DeliveryNotFound extends ApiException
{
    public function __construct(Uuid $deliveryId)
    {
        parent::__construct(sprintf('Delivery "%s" not found.', (string)$deliveryId));
    }

    public function getErrorCode(): string
    {
        return 'DELIVERY_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }
}
