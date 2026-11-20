<?php

declare(strict_types=1);

namespace App\Exceptions;

final class NumberGenerationException extends ApiException
{
    public function __construct(string $reason = 'Could not generate the next invoice number.')
    {
        parent::__construct($reason);
    }

    public function getErrorCode(): string
    {
        return 'NUMBER_GENERATION_FAILED';
    }

    public function getHttpStatus(): int
    {
        return 503;
    }
}
