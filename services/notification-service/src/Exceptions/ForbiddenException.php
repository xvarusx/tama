<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'You do not have permission to perform this action.')
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'FORBIDDEN';
    }

    public function getHttpStatus(): int
    {
        return 403;
    }
}
