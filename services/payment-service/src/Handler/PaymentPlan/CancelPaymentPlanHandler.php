<?php

declare(strict_types=1);

namespace App\Handler\PaymentPlan;

use App\Entity\OutboxEvent;
use App\Entity\PaymentPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Exception\RuntimeException;

/**
 * Use case: cancel a payment plan.
 * All installments are marked as CANCELED.
 */
final readonly class CancelPaymentPlanHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(string $paymentPlanId): PaymentPlan
    {
        $plan = $this->em->find(PaymentPlan::class, $paymentPlanId)
            ?? throw new RuntimeException('Payment plan not found.');

        $plan->cancel();

        $this->em->wrapInTransaction(function () use ($plan): void {
            $this->em->persist($plan);
            $this->em->flush();
        });

        return $plan;
    }
}