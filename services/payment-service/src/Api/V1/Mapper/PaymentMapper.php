<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Documentation\PaymentAllocationDocument;
use App\Api\V1\Documentation\PaymentAttributesDocument;
use App\Api\V1\Documentation\PaymentDocument;
use App\Api\V1\Resource\PaymentAllocationResource;
use App\Api\V1\Resource\PaymentResource;
use App\Entity\Payment;
use App\Entity\PaymentAllocation;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class PaymentMapper
{
    public function toResource(Payment $payment): PaymentResource
    {
        $allocations = array_map(
            $this->allocationToResource(...),
            iterator_to_array($payment->getAllocations())
        );

        return new PaymentResource(
            id: $payment->getId()->toRfc4122(),
            attributes: [
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
                'version' => $payment->getVersion(),
                'allocations' => $allocations,
            ],
        );
    }

    public function toDocument(Payment $payment): PaymentDocument
    {
        return new PaymentDocument(
            id: $payment->getId()->toRfc4122(),
            attributes: new PaymentAttributesDocument(
                number: $payment->getNumber(),
                direction: $payment->getDirection()->value,
                method: $payment->getMethod()->value,
                status: $payment->getStatus()->value,
                partyId: $payment->getPartyId(),
                partyName: $payment->getPartyName(),
                currency: $payment->getCurrency(),
                amount: $payment->getAmount(),
                paidAt: $payment->getPaidAt()?->format(\DATE_ATOM),
                reference: $payment->getReference(),
                version: $payment->getVersion(),
                allocations: array_map(
                    $this->allocationToDocument(...),
                    iterator_to_array($payment->getAllocations())
                ),
            ),
        );
    }

    private function allocationToResource(PaymentAllocation $allocation): array
    {
        return [
            'id' => $allocation->getId()->toRfc4122(),
            'paymentId' => $allocation->getPayment()->getId()->toRfc4122(),
            'installmentId' => $allocation->getInstallment()->getId()->toRfc4122(),
            'invoiceId' => $allocation->getInvoice()->getInvoiceId(),
            'amount' => $allocation->getAmount(),
        ];
    }

    private function allocationToDocument(PaymentAllocation $allocation): PaymentAllocationDocument
    {
        return new PaymentAllocationDocument(
            id: $allocation->getId()->toRfc4122(),
            attributes: new PaymentAllocationAttributesDocument(
                paymentId: $allocation->getPayment()->getId()->toRfc4122(),
                installmentId: $allocation->getInstallment()->getId()->toRfc4122(),
                invoiceId: $allocation->getInvoice()->getInvoiceId(),
                amount: $allocation->getAmount(),
            ),
        );
    }
}