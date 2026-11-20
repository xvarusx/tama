<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Installment;
use App\Entity\PaymentPlan;
use App\Enum\InstallmentStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InstallmentStateMachineTest extends TestCase
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

    public function test_unpaid_can_apply_partial_payment(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $applied = $installment->applyPayment(25000);

        $this->assertSame(25000, $applied);
        $this->assertSame(25000, $installment->getPaidAmount());
        $this->assertSame(InstallmentStatus::PARTIALLY_PAID, $installment->getStatus());
    }

    public function test_unpaid_can_apply_full_payment(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $applied = $installment->applyPayment(50000);

        $this->assertSame(50000, $applied);
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());
    }

    public function test_partial_payment_can_apply_more(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $installment->applyPayment(25000);
        $this->assertSame(InstallmentStatus::PARTIALLY_PAID, $installment->getStatus());

        $installment->applyPayment(25000);
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());
    }

    public function test_paid_cannot_apply_more(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $installment->applyPayment(50000);
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());

        $applied = $installment->applyPayment(10000);
        $this->assertSame(0, $applied); // No change
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());
    }

    public function test_partial_payment_can_apply_additional_to_reach_full(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        // First partial payment
        $installment->applyPayment(30000);
        $this->assertSame(30000, $installment->getPaidAmount());
        $this->assertSame(InstallmentStatus::PARTIALLY_PAID, $installment->getStatus());

        // Second partial payment that completes it
        $installment->applyPayment(20000);
        $this->assertSame(50000, $installment->getPaidAmount());
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());
    }

    public function test_paid_is_terminal(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $installment->applyPayment(50000);
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());

        // PAID should stay PAID
        $installment->applyPayment(10000);
        $this->assertSame(InstallmentStatus::PAID, $installment->getStatus());
    }

    public function test_cancelled_is_terminal(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $installment->setStatus(InstallmentStatus::CANCELED);
        $this->assertSame(InstallmentStatus::CANCELED, $installment->getStatus());

        // CANCELED should not be affected by payment
        $installment->applyPayment(50000);
        $this->assertSame(InstallmentStatus::CANCELED, $installment->getStatus());
    }

    public function test_overdue_logic(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-05'), 50000);

        // Not overdue yet (due date in past, but not paid)
        $today = new DateTimeImmutable('2026-11-10');
        $this->assertTrue($installment->isOverdue($today));

        // Paid - not overdue
        $installment->applyPayment(50000);
        $this->assertFalse($installment->isOverdue($today));

        // Canceled - not overdue
        $installment2 = new Installment($plan, 2, new DateTimeImmutable('2026-11-05'), 50000);
        $installment2->setStatus(InstallmentStatus::CANCELED);
        $this->assertFalse($installment2->isOverdue($today));
    }

    public function test_remaining_calculation(): void
    {
        $plan = $this->createTestPaymentPlan();
        $installment = new Installment($plan, 1, new DateTimeImmutable('2026-11-15'), 50000);

        $this->assertSame(50000, $installment->getRemaining());

        $installment->applyPayment(20000);
        $this->assertSame(30000, $installment->getRemaining());

        $installment->applyPayment(15000);
        $this->assertSame(15000, $installment->getRemaining());

        $installment->applyPayment(15000);
        $this->assertSame(0, $installment->getRemaining());
    }
}