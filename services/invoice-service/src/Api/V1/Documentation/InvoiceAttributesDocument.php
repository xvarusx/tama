<?php

declare(strict_types=1);

namespace App\Api\V1\Documentation;

/** Invoice fields; money is always in minor units. */
final readonly class InvoiceAttributesDocument
{
    /**
     * @param InvoiceLineDocument[] $lines
     * @param CreditNoteDocument[]  $creditNotes
     */
    public function __construct(
        public ?string $number,
        /** draft | issued | partially_paid | paid | voided */
        public string $status,
        /** sales | purchase */
        public string $type,
        public string $orderId,
        public string $partyId,
        public string $partyName,
        public string $currency,
        public ?string $issueDate,
        public ?string $dueDate,
        public int $total,
        public int $amountPaid,
        public int $creditedAmount,
        public int $balance,
        public bool $overpaymentFlagged,
        public bool $overdue,
        public array $lines,
        public array $creditNotes,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
