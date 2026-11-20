<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Documentation\InstallmentDocument;
use App\Api\V1\Documentation\InstallmentAttributesDocument;
use App\Api\V1\Documentation\PaymentPlanAttributesDocument;
use App\Api\V1\Documentation\PaymentPlanDocument;
use App\Api\V1\Resource\InstallmentResource;
use App\Api\V1\Resource\PaymentPlanResource;
use App\Entity\Installment;
use App\Entity\PaymentPlan;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class PaymentPlanMapper
{
    public function toResource(PaymentPlan $plan): PaymentPlanResource
    {
        $installments = array_map(
            $this->installmentToResource(...),
            iterator_to_array($plan->getInstallments())
        );

        return new PaymentPlanResource(
            id: $plan->getId()->toRfc4122(),
            attributes: [
                'invoiceId' => $plan->getInvoiceId(),
                'totalAmount' => $plan->getTotalAmount(),
                'currency' => $plan->getCurrency(),
                'status' => $plan->getStatus(),
                'version' => $plan->getVersion(),
                'isComplete' => $plan->isComplete(),
                'installments' => $installments,
            ],
        );
    }

    public function toDocument(PaymentPlan $plan): PaymentPlanDocument
    {
        return new PaymentPlanDocument(
            id: $plan->getId()->toRfc4122(),
            attributes: new PaymentPlanAttributesDocument(
                invoiceId: $plan->getInvoiceId(),
                totalAmount: $plan->getTotalAmount(),
                currency: $plan->getCurrency(),
                status: $plan->getStatus(),
                version: $plan->getVersion(),
                installments: array_map(
                    $this->installmentToDocument(...),
                    iterator_to_array($plan->getInstallments())
                ),
            ),
        );
    }

    private function installmentToResource(Installment $installment): array
    {
        return [
            'id' => $installment->getId()->toRfc4122(),
            'sequence' => $installment->getSequence(),
            'dueDate' => $installment->getDueDate()->format(\DATE_ATOM),
            'amount' => $installment->getAmount(),
            'paidAmount' => $installment->getPaidAmount(),
            'status' => $installment->getStatus()->value,
            'remaining' => $installment->getRemaining(),
        ];
    }

    private function installmentToDocument(Installment $installment): InstallmentDocument
    {
        return new InstallmentDocument(
            id: $installment->getId()->toRfc4122(),
            attributes: new InstallmentAttributesDocument(
                sequence: $installment->getSequence(),
                dueDate: $installment->getDueDate()->format(\DATE_ATOM),
                amount: $installment->getAmount(),
                paidAmount: $installment->getPaidAmount(),
                status: $installment->getStatus()->value,
                remaining: $installment->getRemaining(),
            ),
        );
    }
}