<?php

declare(strict_types=1);

namespace App\Exceptions;

/** Base class for expected business errors. The subscriber turns them into the error format. */
abstract class ApiException extends \RuntimeException
{
    abstract public function getErrorCode(): string;

    abstract public function getHttpStatus(): int;
}
