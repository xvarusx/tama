<?php

declare(strict_types=1);

namespace App\Handler\Payment;

use App\Api\V1\Request\CreatePaymentRequest;
use App\Entity\OutboxEvent;
use App\Entity\Payment;
use App\Event\PaymentCreatedV1;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Use case: create a new payment.
 * The payment and its outbox event are saved in ONE transaction.
 */
final readonly class CreatePaymentHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(CreatePaymentRequest $request): Payment
    {
        $payment = new Payment(
            number: $request->number,
            direction: $request->direction,
            method: $request->method,
            partyId: $request->partyId,
            partyName: $request->partyName,
            currency: $request->currency,
            amount: $request->amount,
        );
        
        $payment->setReference($request->reference);

        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush(); // generates the id needed by the event payload

            $this->em->persist(PaymentCreatedV1::toOutbox($payment));
            $this->em->flush();
        });

        return $payment;
    }
}