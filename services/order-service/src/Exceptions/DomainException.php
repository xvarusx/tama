<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base exception for domain business rules violations.
 * Thrown when an illegal state transition occurs.
 */
class DomainException extends RuntimeException
{
}