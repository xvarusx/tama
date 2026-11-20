<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;
use App\Entity\Payment;
use App\Entity\PaymentAllocation;
use DateTimeImmutable;

/**
 * Contract v1 of payment.allocated.
 */
final class PaymentAllocatedV1
{
    public const string TYPE = 'payment.allocated';
    public const int VERSION = 1;

    public static function toOutbox(Payment $payment, \App\Entity\Installment $installment, PaymentAllocation $allocation): OutboxEvent
    {
        return new OutboxEvent(self::TYPE, self::VERSION, [
            'paymentId' => $payment->getId()->toRfc4122(),
            'allocationId' => $allocation->getId()->toRfc4122(),
            'installmentId' => $installment->getId()->toRfc4122(),
            'invoiceId' => $allocation->getInvoice()->getInvoiceId(),
            'amount' => $allocation->getAmount(),
            'occurredAt' => (new DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}