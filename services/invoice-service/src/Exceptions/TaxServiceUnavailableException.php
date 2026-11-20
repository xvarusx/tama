<?php

declare(strict_types=1);

namespace App\Exceptions;

final class TaxServiceUnavailableException extends ApiException
{
    public function __construct(string $reason = 'The Tax Service is unavailable.')
    {
        parent::__construct($reason);
    }

    public function getErrorCode(): string
    {
        return 'TAX_SERVICE_UNAVAILABLE';
    }

    public function getHttpStatus(): int
    {
        return 503;
    }
}
