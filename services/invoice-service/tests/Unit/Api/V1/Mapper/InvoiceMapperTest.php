<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\V1\Mapper;

use App\Api\V1\Mapper\InvoiceMapper;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Enum\InvoiceType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceMapperTest extends TestCase
{
    public function testMapsAggregateToResource(): void
    {
        $invoice = new Invoice(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR');
        $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Widget', 2, 1000, 2000, 400));
        $invoice->issue('INV-2026-000001', new DateTimeImmutable('+30 days'));
        $invoice->issueCreditNote(100, 'Correction');

        $resource = new InvoiceMapper()->toResource($invoice);
        $attributes = $resource->attributes;

        self::assertSame($invoice->getId()->toRfc4122(), $resource->id);
        self::assertSame('invoice', $resource->type);
        self::assertSame('INV-2026-000001', $attributes['number']);
        self::assertSame('sales', $attributes['type']);
        self::assertSame('issued', $attributes['status']);
        self::assertSame('EUR', $attributes['currency']);
        self::assertSame(2400, $attributes['total']);
        self::assertSame(0, $attributes['amountPaid']);
        self::assertSame(100, $attributes['creditedAmount']);
        self::assertSame(2300, $attributes['balance']);
        self::assertFalse($attributes['overpaymentFlagged']);
        self::assertFalse($attributes['overdue']);
        self::assertCount(1, $attributes['lines']);
        self::assertSame(2400, $attributes['lines'][0]['total']);
        self::assertCount(1, $attributes['creditNotes']);
        self::assertSame('INV-2026-000001-CN1', $attributes['creditNotes'][0]['number']);
    }
}
