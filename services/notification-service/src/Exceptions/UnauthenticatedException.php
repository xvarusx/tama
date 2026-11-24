<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UnauthenticatedException extends ApiException
{
    public function __construct(string $message = 'Authentication required.')
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'UNAUTHENTICATED';
    }

    public function getHttpStatus(): int
    {
        return 401;
    }
}
