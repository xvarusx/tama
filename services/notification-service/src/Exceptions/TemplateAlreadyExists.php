<?php

declare(strict_types=1);

namespace App\Exceptions;

/** A template with the same (type, channel, locale) natural key already exists. */
final class TemplateAlreadyExists extends ApiException
{
    public function __construct(string $type, string $channel, string $locale)
    {
        parent::__construct(sprintf('A template for "%s" / "%s" / "%s" already exists.', $type, $channel, $locale));
    }

    public function getErrorCode(): string
    {
        return 'TEMPLATE_ALREADY_EXISTS';
    }

    public function getHttpStatus(): int
    {
        return 409;
    }
}
