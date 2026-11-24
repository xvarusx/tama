<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\InvoiceReference;
use App\Entity\PaymentPlan;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentPlanFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            InvoiceReferenceFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $invoiceRef = $manager->getRepository(InvoiceReference::class)->findOneBy(['invoiceId' => '11111111-1111-1111-1111-111111111111']);
        
        $paymentPlan = new PaymentPlan(
            '11111111-1111-1111-1111-111111111111', // invoiceId
            100000, // 1000.00 total
            'USD'
        );

        $manager->persist($paymentPlan);
        $this->addReference('payment-plan-001', $paymentPlan);

        $invoiceRef = $manager->getRepository(InvoiceReference::class)->findOneBy(['invoiceId' => '55555555-5555-5555-5555-555555555555']);
        
        $paymentPlan2 = new PaymentPlan(
            '55555555-5555-5555-5555-555555555555', // invoiceId
            150000, // 1500.00 total
            'USD'
        );

        $manager->persist($paymentPlan2);
        $this->addReference('payment-plan-002', $paymentPlan2);

        $manager->flush();
    }
}