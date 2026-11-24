<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Payment;
use DateTimeImmutable;

/**
 * Contract v1 of payment.failed.
 */
final class PaymentFailedV1
{
    public const string TYPE = 'payment.failed';
    public const int VERSION = 1;

    public static function toOutbox(Payment $payment, string $reason): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'paymentId' => $payment->getId()->toRfc4122(),
            'number' => $payment->getNumber(),
            'status' => $payment->getStatus()->value,
            'amount' => $payment->getAmount(),
            'reason' => $reason,
            'occurredAt' => (new DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}