<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Installment;
use App\Entity\Reminder;
use App\Enum\ReminderKind;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReminderFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            InstallmentFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Create a reminder for overdue installment (INSTALLMENT-4 is past due on Nov 5)
        $installment4 = $this->getReference('installment-4', Installment::class);

        if ($installment4) {
            $reminder1 = new Reminder();
            $reminder1->setInstallment($installment4);
            $reminder1->setKind(ReminderKind::OVERDUE);
            $reminder1->setScheduledFor(new DateTimeImmutable('2026-11-10'));
            $reminder1->markSent();

            $manager->persist($reminder1);
            $this->addReference('reminder-1', $reminder1);
        }

        // Create an upcoming due reminder for INSTALLMENT-5 (due Nov 20)
        $installment5 = $this->getReference('installment-5', Installment::class);

        if ($installment5) {
            $reminder2 = new Reminder();
            $reminder2->setInstallment($installment5);
            $reminder2->setKind(ReminderKind::UPCOMING_DUE);
            $reminder2->setScheduledFor(new DateTimeImmutable('2026-11-18'));
            // Not sent yet - default status is PENDING

            $manager->persist($reminder2);
            $this->addReference('reminder-2', $reminder2);
        }

        // Create reminder for INSTALLMENT-3 (due Nov 25)
        $installment3 = $this->getReference('installment-3', Installment::class);

        if ($installment3) {
            $reminder3 = new Reminder();
            $reminder3->setInstallment($installment3);
            $reminder3->setKind(ReminderKind::UPCOMING_DUE);
            $reminder3->setScheduledFor(new DateTimeImmutable('2026-11-23'));
            $reminder3->markSent();

            $manager->persist($reminder3);
            $this->addReference('reminder-3', $reminder3);
        }

        $manager->flush();
    }
}