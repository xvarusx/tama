<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ContactNotFoundException extends ApiException
{
    public function __construct(string $id, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Contact %s not found.', $id), $previous);
    }

    public function getErrorCode(): string
    {
        return 'CONTACT_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }
}
