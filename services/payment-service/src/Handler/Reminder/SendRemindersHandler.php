<?php

declare(strict_types=1);

namespace App\Handler\Reminder;

use App\Entity\Reminder;
use App\Entity\Setting;
use App\Enum\ReminderKind;
use App\Repository\InstallmentRepository;
use App\Repository\ReminderRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Use case: send reminders for due installments.
 * Scheduled daily to check for overdue installments.
 */
final readonly class SendRemindersHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private InstallmentRepository $installments,
        private ReminderRepository $reminders,
        private SettingRepository $settings,
    ) {}

    /**
     * @param array<string> $scheduledForDate Installment IDs to schedule reminders for
     * @return Reminder[]
     */
    public function __invoke(array $scheduledForDate): array
    {
        $today = new \DateTimeImmutable();
        $reminders = [];

        // Get reminder settings
        $upcomingDays = (int) $this->settings->find('reminder.upcoming_days')?->asInt() ?? 1;
        $overdueDays = (int) $this->settings->find('reminder.overdue_days')?->asInt() ?? 1;

        foreach ($scheduledForDate as $installmentId) {
            // Create UPCOMING_DUE reminder if due within configured days
            $upcomingDate = (new \DateTimeImmutable())->modify("-$upcomingDays days");
            if ($today >= $upcomingDate) {
                $reminder = new Reminder();
                $reminder->setInstallment(
                    $this->em->getReference(\App\Entity\Installment::class, $installmentId)
                );
                $reminder->setKind(ReminderKind::UPCOMING_DUE);
                $reminder->setScheduledFor($upcomingDate);
                $reminder->markSent();

                $this->em->persist($reminder);
                $reminders[] = $reminder;
            }

            // Create OVERDUE reminder if past due
            $overdueDate = (new \DateTimeImmutable())->modify("-$overdueDays days");
            if ($today >= $overdueDate) {
                $existingOverdue = $this->reminders->findOneBy([
                    'installment' => $installmentId,
                    'kind' => ReminderKind::OVERDUE,
                ]);

                if (!$existingOverdue) {
                    $reminder = new Reminder();
                    $reminder->setInstallment(
                        $this->em->getReference(\App\Entity\Installment::class, $installmentId)
                    );
                    $reminder->setKind(ReminderKind::OVERDUE);
                    $reminder->setScheduledFor($today);

                    $this->em->persist($reminder);
                    $reminders[] = $reminder;
                }
            }
        }

        $this->em->wrapInTransaction(function () use ($reminders): void {
            $this->em->flush();
        });

        return $reminders;
    }
}