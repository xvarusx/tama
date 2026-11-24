<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Documentation\PaymentAllocationAttributesDocument;
use App\Api\V1\Documentation\PaymentAllocationDocument;
use App\Api\V1\Resource\PaymentAllocationResource;
use App\Entity\PaymentAllocation;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class PaymentAllocationMapper
{
    public function toResource(PaymentAllocation $allocation): PaymentAllocationResource
    {
        return new PaymentAllocationResource(
            id: $allocation->getId()->toRfc4122(),
            attributes: [
                'paymentId' => $allocation->getPayment()->getId()->toRfc4122(),
                'installmentId' => $allocation->getInstallment()->getId()->toRfc4122(),
                'invoiceId' => $allocation->getInvoice()->getInvoiceId(),
                'amount' => $allocation->getAmount(),
            ],
        );
    }

    public function toDocument(PaymentAllocation $allocation): PaymentAllocationDocument
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