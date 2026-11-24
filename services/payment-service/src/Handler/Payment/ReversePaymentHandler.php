<?php

declare(strict_types=1);

namespace App\Handler\Payment;

use App\Api\V1\Request\ReversePaymentRequest;
use App\Entity\OutboxEvent;
use App\Entity\Payment;
use App\Event\PaymentReversedV1;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Exception\RuntimeException;

/**
 * Use case: reverse a completed payment.
 * The payment and its outbox event are saved in ONE transaction.
 */
final readonly class ReversePaymentHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(ReversePaymentRequest $request): Payment
    {
        $payment = $this->em->find(Payment::class, $request->paymentId)
            ?? throw new RuntimeException('Payment not found.');

        if ($payment->getStatus() !== \App\Enum\PaymentStatus::COMPLETED) {
            throw new RuntimeException('Only a COMPLETED payment can be reversed.');
        }

        $payment->reverse($request->reason);

        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush();

            $this->em->persist(PaymentReversedV1::toOutbox($payment, $request->reason));
            $this->em->flush();
        });

        return $payment;
    }
}