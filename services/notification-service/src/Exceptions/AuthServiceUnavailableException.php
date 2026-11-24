<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

final class AuthServiceUnavailableException extends ApiException
{
    public function __construct(
        string $message = 'The authentication service is unavailable.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return 'AUTH_SERVICE_UNAVAILABLE';
    }

    public function getHttpStatus(): int
    {
        return 503;
    }
}
