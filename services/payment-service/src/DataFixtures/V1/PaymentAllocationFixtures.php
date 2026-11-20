<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\InvoiceReference;
use App\Entity\Installment;
use App\Entity\Payment;
use App\Entity\PaymentAllocation;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentAllocationFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            PaymentFixtures::class,
            InstallmentFixtures::class,
            InvoiceReferenceFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Allocate PYMT-001 (500.00) to INSTALLMENT-1 (300.00)
        $payment1 = $manager->getRepository(Payment::class)->findOneBy(['number' => 'PYMT-001']);
        $installment1 = $this->getReference('installment-1', Installment::class);
        $invoice1 = $this->getReference('invoice-INV-002', InvoiceReference::class);

        if ($payment1 && $installment1 && $invoice1) {
            $allocation1 = $payment1->allocate($installment1, $invoice1, 30000);
            $manager->persist($allocation1);
            $this->addReference('allocation-1', $allocation1);
        }

        // Allocate PYMT-002 (1000.00) to INSTALLMENT-1 remaining (0) and INSTALLMENT-2 (300.00) and INSTALLMENT-3 (400.00) = 700.00
        // Note: INSTALLMENT-1 is already fully paid, so payment should go to INSTALLMENT-2 and INSTALLMENT-3
        $payment2 = $manager->getRepository(Payment::class)->findOneBy(['number' => 'PYMT-002']);
        $installment2 = $this->getReference('installment-2', Installment::class);
        $installment3 = $this->getReference('installment-3', Installment::class);
        $invoice2 = $this->getReference('invoice-INV-001', InvoiceReference::class);

        if ($payment2 && $installment2 && $installment3 && $invoice2) {
            // First allocation: 300.00 to installment 2
            $allocation2 = $payment2->allocate($installment2, $invoice2, 30000);
            $manager->persist($allocation2);
            
            // Second allocation: 400.00 to installment 3
            $allocation3 = $payment2->allocate($installment3, $invoice2, 40000);
            $manager->persist($allocation3);
            
            // Remaining 300.00 unallocated for other installments
            $this->addReference('allocation-2', $allocation2);
            $this->addReference('allocation-3', $allocation3);
        }

        // Allocate for partial payment on INSTALLMENT-4
        $payment4 = $manager->getRepository(Payment::class)->findOneBy(['number' => 'PYMT-004']);
        $installment4 = $this->getReference('installment-4', Installment::class);
        $invoice4 = $this->getReference('invoice-INV-004', InvoiceReference::class);

        // For credit invoices, the allocation direction may be reversed
        // This is a simplified example

        $manager->flush();
    }
}