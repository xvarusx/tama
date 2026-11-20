<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Event\InvoiceCreated;
use App\Exceptions\CreditNoteExceedsBalanceException;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Exceptions\InvoiceNotEditableException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceTest extends TestCase
{
    public function testNewInvoiceIsDraftAndEmitsCreated(): void
    {
        $invoice = $this->invoice();

        self::assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
        self::assertSame($invoice->getOrderId()->toRfc4122(), $invoice->getActiveOrderKey());
        $events = $invoice->pullEvents();
        self::assertInstanceOf(InvoiceCreated::class, $events[0]);
        self::assertSame([], $invoice->pullEvents());
    }

    public function testTotalsAndBalance(): void
    {
        $invoice = $this->invoice();
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'A', 2, 1000, 2000, 400));
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'B', 1, 500, 2000, 100));

        self::assertSame(3000, $invoice->getTotal());
        self::assertSame(0, $invoice->getCreditedAmount());
        self::assertSame(3000, $invoice->getBalance());
    }

    public function testLinePositionsFollowInsertionOrder(): void
    {
        $invoice = $this->invoice();
        $first = new InvoiceLine(Uuid::v7(), 'A', 1, 100, 0, 0);
        $second = new InvoiceLine(Uuid::v7(), 'B', 1, 100, 0, 0);
        $invoice->addLine($first);
        $invoice->addLine($second);

        self::assertSame(0, $first->getPosition());
        self::assertSame(1, $second->getPosition());
        self::assertSame($invoice, $first->getInvoice());
    }

    public function testIssueAssignsNumberDatesAndStatus(): void
    {
        $invoice = $this->issued();

        self::assertSame('INV-2026-000001', $invoice->getNumber());
        self::assertSame(InvoiceStatus::ISSUED, $invoice->getStatus());
        self::assertNotNull($invoice->getIssueDate());
        self::assertNotNull($invoice->getDueDate());
    }

    public function testIssueWithoutLinesThrows(): void
    {
        $this->expectException(InvalidInvoiceTransitionException::class);
        $this->invoice()->issue('INV-2026-000001', new DateTimeImmutable('+30 days'));
    }

    public function testIssueWithDueDateBeforeIssueDateThrows(): void
    {
        $invoice = $this->invoice();
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Widget', 1, 1000, 2000, 200));

        $this->expectException(InvalidInvoiceTransitionException::class);
        $invoice->issue('INV-2026-000001', new DateTimeImmutable('-1 day'));
    }

    public function testIssuedInvoiceIsNotEditable(): void
    {
        $invoice = $this->issued();
        $this->expectException(InvoiceNotEditableException::class);
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Late', 1, 100, 0, 0));
    }

    public function testDraftLinesCanBeReplaced(): void
    {
        $invoice = $this->invoice();
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Old', 1, 100, 0, 0));
        $invoice->replaceLines([new InvoiceLine(Uuid::v7(), 'New', 2, 500, 1000, 100)]);

        self::assertCount(1, $invoice->getLines());
        self::assertSame('New', $invoice->getLines()->first()->getDescription());
        self::assertSame(1100, $invoice->getTotal());
    }

    public function testVoidDraftClearsActiveOrderKey(): void
    {
        $invoice = $this->invoice();
        $invoice->void('Wrong order');

        self::assertSame(InvoiceStatus::VOIDED, $invoice->getStatus());
        self::assertNull($invoice->getActiveOrderKey());
    }

    public function testVoidRequiresReason(): void
    {
        $this->expectException(InvalidInvoiceTransitionException::class);
        $this->invoice()->void('   ');
    }

    public function testCreditNoteReducesBalance(): void
    {
        $invoice = $this->issued();
        $invoice->issueCreditNote(500, 'Price correction');

        self::assertSame(500, $invoice->getCreditedAmount());
        self::assertSame($invoice->getTotal() - 500, $invoice->getBalance());
    }

    public function testCreditNotesAreNumberedPerInvoice(): void
    {
        $invoice = $this->issued();
        $first = $invoice->issueCreditNote(100, 'One');
        $second = $invoice->issueCreditNote(100, 'Two');

        self::assertSame('INV-2026-000001-CN1', $first->getNumber());
        self::assertSame('INV-2026-000001-CN2', $second->getNumber());
    }

    public function testCreditNoteAboveRemainingValueThrows(): void
    {
        $invoice = $this->issued();
        $this->expectException(CreditNoteExceedsBalanceException::class);
        $invoice->issueCreditNote($invoice->getTotal() + 1, 'Too much');
    }

    public function testCreditNoteRejectsNonPositiveAmount(): void
    {
        $invoice = $this->issued();
        $this->expectException(CreditNoteExceedsBalanceException::class);
        $invoice->issueCreditNote(0, 'Nothing');
    }

    public function testCreditNoteRejectedOnDraft(): void
    {
        $this->expectException(InvalidInvoiceTransitionException::class);
        $this->invoice()->issueCreditNote(100, 'Not issued');
    }

    public function testOverdueIsComputed(): void
    {
        $invoice = $this->issued();
        self::assertFalse($invoice->isOverdue(new DateTimeImmutable('+1 day')));
        self::assertTrue($invoice->isOverdue(new DateTimeImmutable('+60 days')));
    }

    public function testDraftIsNeverOverdue(): void
    {
        self::assertFalse($this->invoice()->isOverdue(new DateTimeImmutable('+600 days')));
    }

    private function invoice(): Invoice
    {
        return new Invoice(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR');
    }

    private function issued(): Invoice
    {
        $invoice = $this->invoice();
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Widget', 2, 1000, 2000, 400));
        $invoice->issue('INV-2026-000001', new DateTimeImmutable('+30 days'));
        $invoice->pullEvents();

        return $invoice;
    }
}
