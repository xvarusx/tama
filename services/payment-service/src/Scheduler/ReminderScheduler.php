<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Entity\OutboxEvent;
use App\Event\PaymentReminderRequestedV1;
use App\Repository\InstallmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Scheduler\Attribute\Schedule;

/**
 * Daily scheduler that sends reminders for due installments.
 */
#[Schedule('daily')]
final readonly class ReminderScheduler
{
    public function __construct(
        private EntityManagerInterface $em,
        private InstallmentRepository $installments,
    ) {}

    public function __invoke(): void
    {
        $today = new \DateTimeImmutable();
        $overdueInstallments = $this->installments->findOverdue($today);

        if (empty($overdueInstallments)) {
            return;
        }

        // Create reminder events for overdue installments
        foreach ($overdueInstallments as $installment) {
            $reminderEvent = PaymentReminderRequestedV1::create(
                installmentId: $installment->getId(),
                invoiceId: $installment->getPaymentPlan()->getInvoiceId(),
                dueDate: $installment->getDueDate(),
            );

            $this->em->persist(PaymentReminderRequestedV1::toOutbox($reminderEvent));
        }

        $this->em->wrapInTransaction(function (): void {
            $this->em->flush();
        });
    }
}