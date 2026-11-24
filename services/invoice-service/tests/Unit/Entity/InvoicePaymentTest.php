<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Enum\PaymentResult;
use App\Exceptions\CurrencyMismatchException;
use App\Exceptions\InvalidInvoiceTransitionException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class InvoicePaymentTest extends TestCase
{
    public function testPartialThenFullPayment(): void
    {
        $invoice = $this->issued();
        $first = $invoice->applyPayment(Uuid::v7(), 1000, 'EUR');
        self::assertSame(PaymentResult::APPLIED, $first);
        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->getStatus());

        $second = $invoice->applyPayment(Uuid::v7(), $invoice->getBalance(), 'EUR');
        self::assertSame(PaymentResult::APPLIED, $second);
        self::assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        self::assertSame(0, $invoice->getBalance());
    }

    public function testFullPaymentOnFirstAttempt(): void
    {
        $invoice = $this->issued();
        $result = $invoice->applyPayment(Uuid::v7(), $invoice->getTotal(), 'EUR');

        self::assertSame(PaymentResult::APPLIED, $result);
        self::assertSame(InvoiceStatus::PAID, $invoice->getStatus());
    }

    public function testDuplicatePaymentIsNoOp(): void
    {
        $invoice = $this->issued();
        $paymentId = Uuid::v7();
        $invoice->applyPayment($paymentId, 500, 'EUR');
        $paid = $invoice->getAmountPaid();
        $result = $invoice->applyPayment($paymentId, 500, 'EUR');

        self::assertSame(PaymentResult::DUPLICATE, $result);
        self::assertSame($paid, $invoice->getAmountPaid());
        self::assertCount(1, $invoice->getPayments());
    }

    public function testDuplicatePaymentOnPaidInvoiceIsANoOp(): void
    {
        $invoice = $this->issued();
        $paymentId = Uuid::v7();
        $invoice->applyPayment($paymentId, $invoice->getTotal(), 'EUR');
        self::assertSame(InvoiceStatus::PAID, $invoice->getStatus());

        $result = $invoice->applyPayment($paymentId, $invoice->getTotal(), 'EUR');

        self::assertSame(PaymentResult::DUPLICATE, $result);
        self::assertSame(InvoiceStatus::PAID, $invoice->getStatus());
        self::assertSame($invoice->getTotal(), $invoice->getAmountPaid());
        self::assertCount(1, $invoice->getPayments());
    }

    public function testOverpaymentIsFlaggedAndNotApplied(): void
    {
        $invoice = $this->issued();
        $result = $invoice->applyPayment(Uuid::v7(), $invoice->getBalance() + 1, 'EUR');

        self::assertSame(PaymentResult::FLAGGED, $result);
        self::assertTrue($invoice->isOverpaymentFlagged());
        self::assertSame(0, $invoice->getAmountPaid());
        self::assertSame(InvoiceStatus::ISSUED, $invoice->getStatus());
    }

    public function testFlaggedPaymentIsPersistedAsNotAccepted(): void
    {
        $invoice = $this->issued();
        $invoice->applyPayment(Uuid::v7(), $invoice->getBalance() + 1, 'EUR');

        self::assertCount(1, $invoice->getPayments());
        self::assertFalse($invoice->getPayments()->first()->isAccepted());
    }

    public function testLaterAcceptedPaymentDoesNotClearFlag(): void
    {
        $invoice = $this->issued();
        $invoice->applyPayment(Uuid::v7(), $invoice->getBalance() + 1, 'EUR');
        $invoice->applyPayment(Uuid::v7(), 100, 'EUR');

        self::assertTrue($invoice->isOverpaymentFlagged());
        self::assertSame(100, $invoice->getAmountPaid());
    }

    public function testPaymentOnDraftThrows(): void
    {
        $invoice = $this->invoice();
        $this->expectException(InvalidInvoiceTransitionException::class);
        $invoice->applyPayment(Uuid::v7(), 100, 'EUR');
    }

    public function testPaymentOnVoidedThrows(): void
    {
        $invoice = $this->issued();
        $invoice->void('Cancelled');

        $this->expectException(InvalidInvoiceTransitionException::class);
        $invoice->applyPayment(Uuid::v7(), 100, 'EUR');
    }

    public function testPaymentCurrencyMustMatchInvoice(): void
    {
        $invoice = $this->issued();
        $this->expectException(CurrencyMismatchException::class);
        $invoice->applyPayment(Uuid::v7(), 100, 'USD');
    }

    public function testNonPositivePaymentIsRejected(): void
    {
        $invoice = $this->issued();
        $this->expectException(InvalidInvoiceTransitionException::class);
        $invoice->applyPayment(Uuid::v7(), 0, 'EUR');
    }

    public function testPaymentIdConflictCheckIsScopedToThisInvoice(): void
    {
        $invoice = $this->issued();
        $other = $this->issued();
        $paymentId = Uuid::v7();

        self::assertSame(PaymentResult::APPLIED, $invoice->applyPayment($paymentId, 100, 'EUR'));
        self::assertSame(PaymentResult::APPLIED, $other->applyPayment($paymentId, 100, 'EUR'));
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
