<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Payment;
use DateTimeImmutable;

/**
 * Contract v1 of payment.updated.
 */
final class PaymentUpdatedV1
{
    public const string TYPE = 'payment.updated';
    public const int VERSION = 1;

    public static function toOutbox(Payment $payment): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'paymentId' => $payment->getId()->toRfc4122(),
            'number' => $payment->getNumber(),
            'direction' => $payment->getDirection()->value,
            'method' => $payment->getMethod()->value,
            'status' => $payment->getStatus()->value,
            'partyId' => $payment->getPartyId(),
            'partyName' => $payment->getPartyName(),
            'currency' => $payment->getCurrency(),
            'amount' => $payment->getAmount(),
            'paidAt' => $payment->getPaidAt()?->format(\DATE_ATOM),
            'reference' => $payment->getReference(),
            'occurredAt' => (new DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}