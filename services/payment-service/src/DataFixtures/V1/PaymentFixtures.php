<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\InvoiceReference;
use App\Entity\Payment;
use App\Enum\PaymentDirection;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            InvoiceReferenceFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $payments = [
            [
                'number' => 'PYMT-001',
                'direction' => PaymentDirection::INCOMING,
                'method' => PaymentMethod::CASH,
                'status' => PaymentStatus::COMPLETED,
                'partyId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'partyName' => 'Acme Corp',
                'currency' => 'USD',
                'amount' => 50000, // 500.00
                'paidAt' => new DateTimeImmutable('2026-11-10 09:30:00'),
                'reference' => 'Payment for INV-002',
            ],
            [
                'number' => 'PYMT-002',
                'direction' => PaymentDirection::INCOMING,
                'method' => PaymentMethod::BANK_TRANSFER,
                'status' => PaymentStatus::COMPLETED,
                'partyId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'partyName' => 'Acme Corp',
                'currency' => 'USD',
                'amount' => 100000, // 1000.00
                'paidAt' => new DateTimeImmutable('2026-11-12 14:00:00'),
                'reference' => 'Full payment for INV-001',
            ],
            [
                'number' => 'PYMT-003',
                'direction' => PaymentDirection::INCOMING,
                'method' => PaymentMethod::CHEQUE,
                'status' => PaymentStatus::PENDING,
                'partyId' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'partyName' => 'Globex Inc',
                'currency' => 'EUR',
                'amount' => 200000, // 2000.00 EUR
                'paidAt' => null,
                'reference' => 'Cheque #12345',
            ],
            [
                'number' => 'PYMT-004',
                'direction' => PaymentDirection::INCOMING,
                'method' => PaymentMethod::OTHER,
                'status' => PaymentStatus::COMPLETED,
                'partyId' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                'partyName' => 'Initech LLC',
                'currency' => 'USD',
                'amount' => 25000, // 250.00 credit refund
                'paidAt' => new DateTimeImmutable('2026-11-05 16:45:00'),
                'reference' => 'Credit refund',
            ],
        ];

        foreach ($payments as $data) {
            $payment = new Payment(
                $data['number'],
                $data['direction'],
                $data['method'],
                $data['partyId'],
                $data['partyName'],
                $data['currency'],
                $data['amount']
            );

            if ($data['paidAt'] !== null) {
                $payment->setPaidAt($data['paidAt']);
            }

            if ($data['reference'] !== null) {
                $payment->setReference($data['reference']);
            }

            // Set status (need to access via reflection or setter if exists)
            // For now, let's use the constructor default and update via reflection
            $reflection = new \ReflectionClass($payment);
            $property = $reflection->getProperty('status');
            $property->setAccessible(true);
            $property->setValue($payment, $data['status']);

            $manager->persist($payment);
            $this->addReference('payment-' . $data['number'], $payment);
        }

        $manager->flush();
    }
}