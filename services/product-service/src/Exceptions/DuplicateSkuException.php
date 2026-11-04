<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DuplicateSkuException extends ApiException
{
    public function __construct(string $sku)
    {
        parent::__construct(sprintf('The SKU "%s" is already used by another variant.', $sku));
    }

    public function getErrorCode(): string
    {
        return 'SKU_ALREADY_EXISTS';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
