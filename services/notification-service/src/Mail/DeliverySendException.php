<?php

declare(strict_types=1);

namespace App\Mail;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Throwable;

/** A provider failure. Permanent errors are not retried by the worker. */
final class DeliverySendException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly bool $permanent = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isPermanent(): bool
    {
        return $this->permanent;
    }

    /** Malformed addresses and headers will never succeed on retry. */
    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    /** Anything else (SMTP, transport, provider timeouts) may succeed later. */
    public static function temporary(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }

    /** Map a Mailer exception to the right retry policy. */
    public static function fromThrowable(Throwable $exception): self
    {
        if ($exception instanceof RfcComplianceException || $exception instanceof InvalidArgumentException) {
            return self::permanent($exception->getMessage(), $exception);
        }

        return self::temporary($exception->getMessage(), $exception);
    }
}
