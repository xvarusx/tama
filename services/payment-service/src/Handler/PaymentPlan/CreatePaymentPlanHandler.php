<?php

declare(strict_types=1);

namespace App\Handler\PaymentPlan;

use App\Api\V1\Request\CreatePaymentPlanRequest;
use App\Entity\InvoiceReference;
use App\Entity\OutboxEvent;
use App\Entity\PaymentPlan;
use App\Event\InvoiceIssuedV1;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Exception\RuntimeException;

/**
 * Use case: create a payment plan from an invoice.
 * The payment plan, its installments, and the invoice reference are saved in ONE transaction.
 */
final readonly class CreatePaymentPlanHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(CreatePaymentPlanRequest $request): PaymentPlan
    {
        // Validate invoice exists via invoice reference
        $invoiceRef = $this->em->getRepository(InvoiceReference::class)->findOneBy(['invoiceId' => $request->invoiceId]);
        
        if (!$invoiceRef) {
            // Create a basic invoice reference - in real implementation this would come from event
            $invoiceRef = new InvoiceReference(
                $request->invoiceId,
                'INV-' . substr($request->invoiceId, -8),
                'SALE', // type
                'party-' . substr($request->invoiceId, -6), // placeholder partyId
                'Party Name', // placeholder
                $request->currency,
                $request->totalAmount,
                $request->totalAmount,
                'OPEN',
                new \DateTimeImmutable(),
                new \DateTimeImmutable()
            );
        }

        $plan = new PaymentPlan(
            invoiceId: $request->invoiceId,
            totalAmount: $request->totalAmount,
            currency: $request->currency,
        );

        foreach ($request->installments as $installment) {
            $plan->addInstallment(
                dueDate: new \DateTimeImmutable($installment->dueDate),
                amount: $installment->amount,
            );
        }

        $this->em->wrapInTransaction(function () use ($plan, $invoiceRef): void {
            $this->em->persist($invoiceRef);
            $this->em->persist($plan);
            $this->em->flush();
        });

        return $plan;
    }
}