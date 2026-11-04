<?php

declare(strict_types=1);

namespace App\Exceptions;

/** A brand, unit or category id in the request does not exist. */
final class ReferenceNotFoundException extends ApiException
{
    public function __construct(string $what, int $id)
    {
        parent::__construct(sprintf('%s %d does not exist.', $what, $id));
    }

    public function getErrorCode(): string
    {
        return 'REFERENCE_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 422;
    }
}
