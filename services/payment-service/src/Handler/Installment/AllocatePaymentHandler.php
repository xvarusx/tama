<?php

declare(strict_types=1);

namespace App\Handler\Installment;

use App\Entity\InvoiceReference;
use App\Entity\OutboxEvent;
use App\Entity\Payment;
use App\Entity\PaymentAllocation;
use App\Entity\Installment;
use App\Event\PaymentAllocatedV1;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Exception\RuntimeException;

/**
 * Use case: allocate a payment to installments.
 * Follows the allocation rule: payment allocates to oldest unpaid installment first.
 */
final readonly class AllocatePaymentHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(string $paymentId, int $amount): PaymentAllocation
    {
        $payment = $this->em->find(Payment::class, $paymentId)
            ?? throw new RuntimeException('Payment not found.');

        if ($payment->getStatus() !== \App\Enum\PaymentStatus::COMPLETED) {
            throw new RuntimeException('Only a COMPLETED payment can be allocated.');
        }

        // Find the oldest unpaid installment with remaining balance
        $installment = $this->em->getRepository(Installment::class)->findOldestUnpaid();
        
        if (!$installment) {
            throw new RuntimeException('No unpaid installments found.');
        }

        // Get or create invoice reference for this installment
        $invoiceRef = $installment->getPaymentPlan()->getInvoiceId();
        
        $allocation = $payment->allocate(
            installment: $installment,
            invoice: $this->getOrCreateInvoiceRef($invoiceRef),
            amount: min($amount, $installment->getRemaining()),
        );

        $this->em->wrapInTransaction(function () use ($payment, $allocation): void {
            $this->em->persist($allocation);
            $this->em->persist($payment);
            $this->em->flush();

            $this->em->persist(PaymentAllocatedV1::toOutbox($payment, $installment, $allocation));
            $this->em->flush();
        });

        return $allocation;
    }

    private function getOrCreateInvoiceRef(string $invoiceId): InvoiceReference
    {
        $invoiceRef = $this->em->getRepository(InvoiceReference::class)->findOneBy(['invoiceId' => $invoiceId]);
        
        if ($invoiceRef) {
            return $invoiceRef;
        }

        // Create placeholder reference (in real implementation, this would come from event)
        return new InvoiceReference(
            $invoiceId,
            'INV-' . substr($invoiceId, -8),
            'SALE',
            'party-' . substr($invoiceId, -6),
            'Party Name',
            'USD',
            10000,
            10000,
            'OPEN',
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
    }
}