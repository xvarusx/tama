<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event;

use App\Event\CreditNoteIssued;
use App\Event\InvoiceCreated;
use App\Event\InvoiceIssued;
use App\Event\InvoicePaid;
use App\Event\InvoiceVoided;
use PHPUnit\Framework\TestCase;

final class InvoiceEventsTest extends TestCase
{
    public function testCreatedEventMapsToOutbox(): void
    {
        $event = new InvoiceCreated('inv-1', 'order-1');
        $outbox = $event->toOutbox();

        self::assertSame('invoice.created', $outbox->getEventType());
        self::assertSame(1, $outbox->getEventVersion());
        self::assertSame(['invoiceId' => 'inv-1', 'orderId' => 'order-1'], $outbox->getPayload());
    }

    public function testIssuedEventMapsToOutbox(): void
    {
        $outbox = new InvoiceIssued('inv-1', 'INV-2026-000001')->toOutbox();

        self::assertSame('invoice.issued', $outbox->getEventType());
        self::assertSame(1, $outbox->getEventVersion());
        self::assertSame(['invoiceId' => 'inv-1', 'number' => 'INV-2026-000001'], $outbox->getPayload());
    }

    public function testPaidEventMapsToOutbox(): void
    {
        $outbox = new InvoicePaid('inv-1')->toOutbox();

        self::assertSame('invoice.paid', $outbox->getEventType());
        self::assertSame(1, $outbox->getEventVersion());
        self::assertSame(['invoiceId' => 'inv-1'], $outbox->getPayload());
    }

    public function testVoidedEventMapsToOutbox(): void
    {
        $outbox = new InvoiceVoided('inv-1', 'Wrong order')->toOutbox();

        self::assertSame('invoice.voided', $outbox->getEventType());
        self::assertSame(1, $outbox->getEventVersion());
        self::assertSame(['invoiceId' => 'inv-1', 'reason' => 'Wrong order'], $outbox->getPayload());
    }

    public function testCreditNoteEventMapsToOutbox(): void
    {
        $outbox = new CreditNoteIssued('inv-1', 500)->toOutbox();

        self::assertSame('invoice.credit_note_issued', $outbox->getEventType());
        self::assertSame(1, $outbox->getEventVersion());
        self::assertSame(['invoiceId' => 'inv-1', 'amount' => 500], $outbox->getPayload());
    }
}
