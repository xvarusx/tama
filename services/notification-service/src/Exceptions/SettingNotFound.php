<?php

declare(strict_types=1);

namespace App\Exceptions;

final class SettingNotFound extends ApiException
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf('Setting "%s" not found.', $key));
    }

    public function getErrorCode(): string
    {
        return 'SETTING_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }
}
