<?php

declare(strict_types=1);

namespace App\Handler\Payment;

use App\Api\V1\Request\RecordPaymentRequest;
use App\Entity\OutboxEvent;
use App\Entity\Payment;
use App\Event\PaymentCompletedV1;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Exception\RuntimeException;

/**
 * Use case: record a cheque payment (mark as completed).
 * The payment and its outbox event are saved in ONE transaction.
 */
final readonly class RecordPaymentHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(RecordPaymentRequest $request): Payment
    {
        $payment = $this->em->find(Payment::class, $request->paymentId)
            ?? throw new RuntimeException('Payment not found.');

        if ($payment->getStatus() !== \App\Enum\PaymentStatus::PENDING) {
            throw new RuntimeException('Only a PENDING payment can be recorded.');
        }

        $payment->setReference($request->reference ?? $request->chequeNumber);
        $payment->confirm(); // marks as COMPLETED

        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush();

            $this->em->persist(PaymentCompletedV1::toOutbox($payment));
            $this->em->flush();
        });

        return $payment;
    }
}