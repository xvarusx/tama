<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Installment;
use App\Entity\PaymentPlan;
use App\Enum\InstallmentStatus;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InstallmentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            PaymentPlanFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $paymentPlan = $manager->getRepository(PaymentPlan::class)->findOneBy(['invoiceId' => '11111111-1111-1111-1111-111111111111']);
        
        if ($paymentPlan) {
            // Create 3 installments for invoice 001
            $installment1 = new Installment(
                $paymentPlan,
                1,
                new DateTimeImmutable('2026-11-15'),
                30000 // 300.00
            );

            $installment2 = new Installment(
                $paymentPlan,
                2,
                new DateTimeImmutable('2026-11-20'),
                30000 // 300.00
            );

            $installment3 = new Installment(
                $paymentPlan,
                3,
                new DateTimeImmutable('2026-11-25'),
                40000 // 400.00
            );

            // Mark installment 1 as paid
            $installment1->applyPayment(30000);

            $manager->persist($installment1);
            $manager->persist($installment2);
            $manager->persist($installment3);
            
            $this->addReference('installment-1', $installment1);
            $this->addReference('installment-2', $installment2);
            $this->addReference('installment-3', $installment3);
        }

        // Create another payment plan with different installment status
        $paymentPlan2 = $manager->getRepository(PaymentPlan::class)->findOneBy(['invoiceId' => '55555555-5555-5555-5555-555555555555']);
        
        if ($paymentPlan2) {
            $installment4 = new Installment(
                $paymentPlan2,
                1,
                new DateTimeImmutable('2026-11-05'), // Past due
                50000 // 500.00
            );

            $installment5 = new Installment(
                $paymentPlan2,
                2,
                new DateTimeImmutable('2026-11-20'),
                50000 // 500.00
            );

            // Mark installment 4 as partially paid
            $installment4->applyPayment(25000); // Only half paid

            $manager->persist($installment4);
            $manager->persist($installment5);
            
            $this->addReference('installment-4', $installment4);
            $this->addReference('installment-5', $installment5);
        }

        $manager->flush();
    }
}