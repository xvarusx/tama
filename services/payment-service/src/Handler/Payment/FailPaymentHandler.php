<?php

declare(strict_types=1);

namespace App\Handler\Payment;

use App\Api\V1\Request\FailPaymentRequest;
use App\Entity\OutboxEvent;
use App\Entity\Payment;
use App\Event\PaymentFailedV1;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Exception\RuntimeException;

/**
 * Use case: fail a payment (cheque bounced, transfer rejected).
 * The payment and its outbox event are saved in ONE transaction.
 */
final readonly class FailPaymentHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(FailPaymentRequest $request): Payment
    {
        $payment = $this->em->find(Payment::class, $request->paymentId)
            ?? throw new RuntimeException('Payment not found.');

        if ($payment->getStatus() !== \App\Enum\PaymentStatus::PENDING) {
            throw new RuntimeException('Only a PENDING payment can be failed.');
        }

        $payment->fail($request->reason);

        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush();

            $this->em->persist(PaymentFailedV1::toOutbox($payment, $request->reason));
            $this->em->flush();
        });

        return $payment;
    }
}