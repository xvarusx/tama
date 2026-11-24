<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class ApiException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($this->getHttpStatus(), $message, $previous);
    }

    abstract public function getErrorCode(): string;

    abstract public function getHttpStatus(): int;
}
