<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\InvoiceReference;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InvoiceReferenceTest extends TestCase
{
    public function test_create_invoice_reference(): void
    {
        $invoiceRef = new InvoiceReference(
            '11111111-1111-1111-1111-111111111111',
            'INV-001',
            'STANDARD',
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            100000,
            100000,
            'ISSUED'
        );

        $this->assertSame('11111111-1111-1111-1111-111111111111', $invoiceRef->getInvoiceId());
        $this->assertSame('INV-001', $invoiceRef->getNumber());
        $this->assertSame('STANDARD', $invoiceRef->getType());
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $invoiceRef->getPartyId());
        $this->assertSame('Acme Corp', $invoiceRef->getPartyName());
        $this->assertSame('USD', $invoiceRef->getCurrency());
        $this->assertSame(100000, $invoiceRef->getTotal());
        $this->assertSame(100000, $invoiceRef->getOutstanding());
        $this->assertSame('ISSUED', $invoiceRef->getStatus());
    }

    public function test_refresh_from_event(): void
    {
        $invoiceRef = new InvoiceReference(
            '11111111-1111-1111-1111-111111111111',
            'INV-001',
            'STANDARD',
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            100000,
            100000,
            'ISSUED'
        );

        $eventData = [
            'invoiceId' => '22222222-2222-2222-2222-222222222222',
            'number' => 'INV-002',
            'type' => 'STANDARD',
            'partyId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'partyName' => 'Acme Corp',
            'currency' => 'EUR',
            'total' => 200000,
            'outstanding' => 200000,
            'status' => 'ISSUED',
        ];

        $invoiceRef->refreshFrom($eventData);

        $this->assertSame('22222222-2222-2222-2222-222222222222', $invoiceRef->getInvoiceId());
        $this->assertSame('INV-002', $invoiceRef->getNumber());
        $this->assertSame('EUR', $invoiceRef->getCurrency());
        $this->assertSame(200000, $invoiceRef->getTotal());
        $this->assertSame('ISSUED', $invoiceRef->getStatus());
    }

    public function test_getters_setters(): void
    {
        $invoiceRef = new InvoiceReference(
            '11111111-1111-1111-1111-111111111111',
            'INV-001',
            'STANDARD',
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            100000,
            100000,
            'ISSUED'
        );

        $invoiceRef->setOutstanding(50000);
        $this->assertSame(50000, $invoiceRef->getOutstanding());

        $invoiceRef->setStatus('PAID');
        $this->assertSame('PAID', $invoiceRef->getStatus());
    }
}