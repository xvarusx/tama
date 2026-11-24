<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\InvoiceReference;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class InvoiceReferenceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $invoices = [
            [
                'invoiceId' => '11111111-1111-1111-1111-111111111111',
                'number' => 'INV-001',
                'type' => 'STANDARD',
                'partyId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'partyName' => 'Acme Corp',
                'currency' => 'USD',
                'total' => 100000, // 1000.00 in cents
                'outstanding' => 100000,
                'status' => 'ISSUED',
            ],
            [
                'invoiceId' => '22222222-2222-2222-2222-222222222222',
                'number' => 'INV-002',
                'type' => 'STANDARD',
                'partyId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'partyName' => 'Acme Corp',
                'currency' => 'USD',
                'total' => 50000, // 500.00 in cents
                'outstanding' => 30000, // Partially paid
                'status' => 'PARTIALLY_PAID',
            ],
            [
                'invoiceId' => '33333333-3333-3333-3333-333333333333',
                'number' => 'INV-003',
                'type' => 'STANDARD',
                'partyId' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'partyName' => 'Globex Inc',
                'currency' => 'EUR',
                'total' => 200000,
                'outstanding' => 0,
                'status' => 'PAID',
            ],
            [
                'invoiceId' => '44444444-4444-4444-4444-444444444444',
                'number' => 'INV-004',
                'type' => 'CREDIT',
                'partyId' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                'partyName' => 'Initech LLC',
                'currency' => 'USD',
                'total' => -25000, // 250.00 credit
                'outstanding' => -25000,
                'status' => 'ISSUED',
            ],
            [
                'invoiceId' => '55555555-5555-5555-5555-555555555555',
                'number' => 'INV-005',
                'type' => 'STANDARD',
                'partyId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'partyName' => 'Acme Corp',
                'currency' => 'USD',
                'total' => 150000,
                'outstanding' => 150000,
                'status' => 'PENDING',
            ],
        ];

        foreach ($invoices as $data) {
            $invoice = new InvoiceReference(
                $data['invoiceId'],
                $data['number'],
                $data['type'],
                $data['partyId'],
                $data['partyName'],
                $data['currency'],
                $data['total'],
                $data['outstanding'],
                $data['status'],
                new DateTimeImmutable('2026-11-01 10:00:00'),
                new DateTimeImmutable('2026-11-01 10:00:00')
            );
            $manager->persist($invoice);
            $this->addReference('invoice-' . $data['number'], $invoice);
        }

        $manager->flush();
    }
}