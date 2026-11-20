<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CreditNote;
use App\Entity\Invoice;
use App\Enum\InvoiceType;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreditNoteTest extends TestCase
{
    public function testCreditNoteRejectsNonPositiveAmount(): void
    {
        $invoice = new Invoice(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR');

        $this->expectException(InvalidArgumentException::class);
        new CreditNote($invoice, 'INV-2026-000001-CN1', 0, 'Correction');
    }

    public function testCreditNoteRejectsBlankReason(): void
    {
        $invoice = new Invoice(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR');

        $this->expectException(InvalidArgumentException::class);
        new CreditNote($invoice, 'INV-2026-000001-CN1', 100, '  ');
    }

    public function testCreditNoteExposesItsFields(): void
    {
        $invoice = new Invoice(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR');
        $note = new CreditNote($invoice, 'INV-2026-000001-CN1', 100, 'Correction');

        self::assertSame($invoice, $note->getInvoice());
        self::assertSame('INV-2026-000001-CN1', $note->getNumber());
        self::assertSame(100, $note->getAmount());
        self::assertSame('Correction', $note->getReason());
        self::assertSame(new DateTimeImmutable()->format('Y-m-d'), $note->getIssuedAt()->format('Y-m-d'));
    }
}
