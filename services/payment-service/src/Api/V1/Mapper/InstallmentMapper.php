<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Documentation\InstallmentAttributesDocument;
use App\Api\V1\Documentation\InstallmentDocument;
use App\Api\V1\Resource\InstallmentResource;
use App\Entity\Installment;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class InstallmentMapper
{
    public function toResource(Installment $installment): InstallmentResource
    {
        return new InstallmentResource(
            id: $installment->getId()->toRfc4122(),
            attributes: [
                'sequence' => $installment->getSequence(),
                'dueDate' => $installment->getDueDate()->format(\DATE_ATOM),
                'amount' => $installment->getAmount(),
                'paidAmount' => $installment->getPaidAmount(),
                'status' => $installment->getStatus()->value,
                'remaining' => $installment->getRemaining(),
                'paymentPlanId' => $installment->getPaymentPlan()->getId()->toRfc4122(),
                'isOverdue' => $installment->isOverdue(new \DateTimeImmutable()),
            ],
        );
    }

    public function toDocument(Installment $installment): InstallmentDocument
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