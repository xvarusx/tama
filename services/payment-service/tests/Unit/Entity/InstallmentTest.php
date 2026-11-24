<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Installment;
use App\Entity\PaymentPlan;
use App\Enum\InstallmentStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InstallmentTest extends TestCase
{
    private function createTestPaymentPlan(): PaymentPlan
    {
        $plan = new PaymentPlan(
            '11111111-1111-1111-1111-111111111111',
            100000,
            'USD'
        );
        return $plan;
    }

    public function test_create_installment(): void
    {
        $plan = $this->createTestPaymentPlan();
        $dueDate = new DateTimeImmutable('2026-11-15');

        $installment = new Installment($plan, 1, $dueDate, 50000);

        $this->assertSame(1, $installment->getSequence());
        $this->assertSame($dueDate, $installment->getDueDate());
        $this->assertSame(50000, $installment->getAmount());
        $this->assertSame(0, $installment->getPaidAmount());
        $this->assertSame(InstallmentStatus::UNPAID, $installment->getStatus());
        $this->assertSame(50000, $installment->getRemaining()); // Remaining = amount - paidAmount = 50000 - 0
    }

    public function test_apply_full_payment(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $applied = $installment->applyPayment(50000);

        $this->assertSame(50000, $applied);
        $this->assertSame(50000, $installment->getPaidAmount());
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());
        $this->assertSame(0, $installment->getRemaining());
    }

    public function test_apply_partial_payment(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $applied = $installment->applyPayment(25000);

        $this->assertSame(25000, $applied);
        $this->assertSame(25000, $installment->getPaidAmount());
        $this->assertSame(InstallmentStatus::PARTIALLY_PAID, $installment->getStatus());
        $this->assertSame(25000, $installment->getRemaining());
    }

    public function test_apply_excess_payment_returns_remaining(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $applied = $installment->applyPayment(75000);

        $this->assertSame(50000, $applied); // Only 50000 can be applied
    }

    public function test_apply_payment_to_canceled_installment_returns_zero(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);
        $installment->setStatus(InstallmentStatus::CANCELED);

        $applied = $installment->applyPayment(50000);

        $this->assertSame(0, $applied);
    }

    public function test_apply_payment_to_paid_installment_returns_zero(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);
        $installment->setStatus(InstallmentStatus::PAID);

        $applied = $installment->applyPayment(50000);

        $this->assertSame(0, $applied);
    }

    public function test_is_overdue_returns_true_for_unpaid_past_due(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-05'), 50000);

        $today = new DateTimeImmutable('2026-11-10');

        $this->assertTrue($installment->isOverdue($today));
    }

    public function test_is_overdue_returns_false_for_paid(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-05'), 50000);
        $installment->setStatus(InstallmentStatus::PAID);

        $today = new DateTimeImmutable('2026-11-10');

        $this->assertFalse($installment->isOverdue($today));
    }

    public function test_is_overdue_returns_false_for_due_today(): void
    {
        $plan = $this->createTestPaymentPlan();
        $dueDate = new DateTimeImmutable('2026-11-10');
        $installment = new Installment($plan, 1, $dueDate, 50000);

        $today = new DateTimeImmutable('2026-11-10');

        // Due date is today, not overdue (only past due is overdue)
        $this->assertFalse($installment->isOverdue($today));
    }

    public function test_is_overdue_returns_false_for_future_due(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-20'), 50000);

        $today = new DateTimeImmutable('2026-11-10');

        $this->assertFalse($installment->isOverdue($today));
    }

    public function test_set_paid_amount(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $installment->setPaidAmount(25000);
        $this->assertSame(25000, $installment->getPaidAmount());

        // Status should still be UNPAID since paidAmount < amount
        $this->assertSame(InstallmentStatus::UNPAID, $installment->getStatus());

        // Now pay the remainder using applyPayment which updates both amount and status
        $installment->applyPayment(25000);
        $this->assertSame(50000, $installment->getPaidAmount());
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());
    }

    public function test_set_due_date(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $newDueDate = new DateTimeImmutable('2026-12-01');
        $installment->setDueDate($newDueDate);

        $this->assertSame($newDueDate, $installment->getDueDate());
    }
}