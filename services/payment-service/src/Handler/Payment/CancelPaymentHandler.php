<?php

declare(strict_types=1);

namespace App\Handler\Payment;

use App\Entity\OutboxEvent;
use App\Entity\Payment;
use App\Event\PaymentCanceledV1;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Exception\RuntimeException;

/**
 * Use case: cancel a payment.
 * The payment and its outbox event are saved in ONE transaction.
 */
final readonly class CancelPaymentHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(string $paymentId): Payment
    {
        $payment = $this->em->find(Payment::class, $paymentId)
            ?? throw new RuntimeException('Payment not found.');

        $payment->cancel();

        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush();

            $this->em->persist(PaymentCanceledV1::toOutbox($payment));
            $this->em->flush();
        });

        return $payment;
    }
}