<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\InvoiceResource;
use App\Entity\CreditNote;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class InvoiceMapper
{
    public function toResource(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource(
            id: $invoice->getId()->toRfc4122(),
            attributes: [
                'number' => $invoice->getNumber(),
                'type' => $invoice->getType()->value,
                'status' => $invoice->getStatus()->value,
                'orderId' => $invoice->getOrderId()->toRfc4122(),
                'partyId' => $invoice->getPartyId()->toRfc4122(),
                'partyName' => $invoice->getPartyName(),
                'currency' => $invoice->getCurrency(),
                'issueDate' => $invoice->getIssueDate()?->format(\DATE_ATOM),
                'dueDate' => $invoice->getDueDate()?->format(\DATE_ATOM),
                'total' => $invoice->getTotal(),
                'amountPaid' => $invoice->getAmountPaid(),
                'creditedAmount' => $invoice->getCreditedAmount(),
                'balance' => $invoice->getBalance(),
                'overpaymentFlagged' => $invoice->isOverpaymentFlagged(),
                'overdue' => $invoice->isOverdue(),
                'lines' => array_values(array_map(
                    $this->toLine(...),
                    $invoice->getLines()->toArray(),
                )),
                'creditNotes' => array_values(array_map(
                    $this->toCreditNote(...),
                    $invoice->getCreditNotes()->toArray(),
                )),
                'createdAt' => $invoice->getCreatedAt()->format(\DATE_ATOM),
                'updatedAt' => $invoice->getUpdatedAt()->format(\DATE_ATOM),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function toLine(InvoiceLine $line): array
    {
        return [
            'productId' => $line->getProductId()->toRfc4122(),
            'description' => $line->getDescription(),
            'quantity' => $line->getQuantity(),
            'unitPrice' => $line->getUnitPrice(),
            'taxRateBps' => $line->getTaxRateBps(),
            'taxAmount' => $line->getTaxAmount(),
            'subtotal' => $line->getSubtotal(),
            'total' => $line->getTotal(),
            'position' => $line->getPosition(),
        ];
    }

    /** @return array<string, mixed> */
    private function toCreditNote(CreditNote $creditNote): array
    {
        return [
            'number' => $creditNote->getNumber(),
            'amount' => $creditNote->getAmount(),
            'reason' => $creditNote->getReason(),
            'issuedAt' => $creditNote->getIssuedAt()->format(\DATE_ATOM),
        ];
    }
}
