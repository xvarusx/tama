<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\InvoiceReference;
use App\Entity\Installment;
use App\Entity\Payment;
use App\Entity\PaymentPlan;
use App\Enum\InstallmentStatus;
use App\Enum\PaymentDirection;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function test_create_payment(): void
    {
        $payment = new Payment(
            'PYMT-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $this->assertSame('PYMT-001', $payment->getNumber());
        $this->assertSame(PaymentDirection::INCOMING, $payment->getDirection());
        $this->assertSame(PaymentMethod::CASH, $payment->getMethod());
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $payment->getPartyId());
        $this->assertSame('Acme Corp', $payment->getPartyName());
        $this->assertSame('USD', $payment->getCurrency());
        $this->assertSame(50000, $payment->getAmount());
        $this->assertNull($payment->getPaidAt());
        $this->assertNull($payment->getReference());
        $this->assertSame(0, $payment->getVersion());
    }

    public function test_confirm_payment(): void
    {
        $payment = new Payment(
            'PYMT-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->confirm();

        $this->assertSame(PaymentStatus::COMPLETED, $payment->getStatus());
        $this->assertInstanceOf(DateTimeImmutable::class, $payment->getPaidAt());
        $events = $payment->pullEvents();
        $this->assertCount(1, $events);
    }

    public function test_fail_payment(): void
    {
        $payment = new Payment(
            'PYMT-003',
            PaymentDirection::INCOMING,
            PaymentMethod::CHEQUE,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->fail('Cheque bounced');

        $this->assertSame(PaymentStatus::FAILED, $payment->getStatus());
        $events = $payment->pullEvents();
        $this->assertCount(1, $events);
    }

    public function test_reverse_payment(): void
    {
        $payment = new Payment(
            'PYMT-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->confirm();
        $payment->reverse('Customer refund');

        $this->assertSame(PaymentStatus::REVERSED, $payment->getStatus());
        $events = $payment->pullEvents();
        $this->assertCount(2, $events);
    }

    public function test_cancel_payment(): void
    {
        $payment = new Payment(
            'PYMT-003',
            PaymentDirection::INCOMING,
            PaymentMethod::CHEQUE,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->cancel();

        $this->assertSame(PaymentStatus::CANCELED, $payment->getStatus());
    }

    public function test_cancel_completed_payment_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A COMPLETED payment cannot be canceled.');

        $payment = new Payment(
            'PYMT-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->confirm();
        $payment->cancel();
    }

    public function test_confirm_completed_payment_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only a PENDING payment can be confirmed.');

        $payment = new Payment(
            'PYMT-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->confirm();
        $payment->confirm();
    }

    public function test_reverse_pending_payment_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only a COMPLETED payment can be reversed.');

        $payment = new Payment(
            'PYMT-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->reverse('Oops');
    }

    public function test_set_reference(): void
    {
        $payment = new Payment(
            'PYMT-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->setReference('Payment for INV-002');
        $this->assertSame('Payment for INV-002', $payment->getReference());
    }

    public function test_pull_events_clears(): void
    {
        $payment = new Payment(
            'PYMT-001',
            PaymentDirection::INCOMING,
            PaymentMethod::CASH,
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'Acme Corp',
            'USD',
            50000
        );

        $payment->confirm();
        $payment->reverse('Test');

        $events = $payment->pullEvents();
        $this->assertCount(2, $events);

        // Pull again should return empty
        $this->assertCount(0, $payment->pullEvents());
    }
}