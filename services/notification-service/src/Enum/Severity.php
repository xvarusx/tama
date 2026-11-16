<?php

declare(strict_types=1);

namespace App\Enum;

use ValueError;

enum Severity: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case CRITICAL = 'critical';

    public static function default(): self
    {
        return self::INFO;
    }

    /**
     * Missing values fall back to INFO; anything else must be a known severity.
     *
     * @throws ValueError when the value is not a severity
     */
    public static function fromString(?string $value): self
    {
        if ($value === null || trim($value) === '') {
            return self::default();
        }

        return self::from(strtolower(trim($value)));
    }
}
