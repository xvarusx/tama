<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\Uid\Uuid;

final class TemplateNotFound extends ApiException
{
    public function __construct(Uuid $templateId)
    {
        parent::__construct(sprintf('Template "%s" not found.', (string)$templateId));
    }

    public function getErrorCode(): string
    {
        return 'TEMPLATE_NOT_FOUND';
    }

    public function getHttpStatus(): int
    {
        return 404;
    }
}
