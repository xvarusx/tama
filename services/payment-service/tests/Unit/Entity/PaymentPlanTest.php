<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Installment;
use App\Entity\PaymentPlan;
use App\Enum\InstallmentStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PaymentPlanTest extends TestCase
{
    public function test_create_payment_plan(): void
    {
        $plan = new PaymentPlan(
            '11111111-1111-1111-1111-111111111111',
            100000,
            'USD'
        );

        $this->assertSame('11111111-1111-1111-1111-111111111111', $plan->getInvoiceId());
        $this->assertSame(100000, $plan->getTotalAmount());
        $this->assertSame('USD', $plan->getCurrency());
        $this->assertSame('ACTIVE', $plan->getStatus());
        $this->assertSame(0, $plan->getVersion());
    }

    public function test_add_installment(): void
    {
        $plan = new PaymentPlan(
            '11111111-1111-1111-1111-111111111111',
            100000,
            'USD'
        );

        $installment = $plan->addInstallment(
            new DateTimeImmutable('2026-11-15'),
            30000
        );

        $this->assertCount(1, $plan->getInstallments());
        $this->assertSame(1, $installment->getSequence());
        $this->assertSame(30000, $installment->getAmount());
        $this->assertSame(InstallmentStatus::UNPAID, $installment->getStatus());
    }

    public function test_is_complete_returns_false_for_unpaid(): void
    {
        $plan = new PaymentPlan(
            '11111111-1111-1111-1111-111111111111',
            100000,
            'USD'
        );

        $plan->addInstallment(new DateTimeImmutable('2026-11-15'), 50000);

        $this->assertFalse($plan->isComplete());
    }

    public function test_is_complete_returns_true_when_all_paid(): void
    {
        $plan = new PaymentPlan(
            '11111111-1111-1111-1111-111111111111',
            100000,
            'USD'
        );

        $installment1 = $plan->addInstallment(new DateTimeImmutable('2026-11-15'), 50000);
        $installment2 = $plan->addInstallment(new DateTimeImmutable('2026-11-20'), 50000);

        $installment1->applyPayment(50000);
        $installment2->applyPayment(50000);

        $this->assertTrue($plan->isComplete());
    }

    public function test_cancel_changes_status(): void
    {
        $plan = new PaymentPlan(
            '11111111-1111-1111-1111-111111111111',
            100000,
            'USD'
        );

        $plan->addInstallment(new DateTimeImmutable('2026-11-15'), 50000);

        $plan->cancel();

        $this->assertSame('CANCELED', $plan->getStatus());
    }

    public function test_reschedule_installments(): void
    {
        $plan = new PaymentPlan(
            '11111111-1111-1111-1111-111111111111',
            100000,
            'USD'
        );

        $installment1 = $plan->addInstallment(new DateTimeImmutable('2026-11-15'), 50000);
        // Get the installment's UUID (already generated in constructor)
        $installmentId = $installment1->getId();

        $newDueDate = new DateTimeImmutable('2026-12-01');
        // Pass the string representation of the UUID as the key
        $plan->reschedule([$installmentId->toString() => $newDueDate]);

        $this->assertSame($newDueDate, $installment1->getDueDate());
    }
}