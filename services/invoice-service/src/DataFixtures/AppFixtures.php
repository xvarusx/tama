<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Enum\InvoiceType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Seeds the invoice service with a deterministic data set:
 *  - 30 invoices, covering every InvoiceStatus via the domain state machine (no reflection),
 *  - 1 line per invoice plus a second line on every third invoice => 40 lines in total.
 *
 * All state transitions go through public domain methods: addLine(), issue(),
 * void() and applyPayment(). Numbers are only assigned by issue(), so draft and
 * voided invoices keep a NULL (unique) number. Every invoice gets a fresh
 * orderId (Uuid::v7) so the unique activeOrderKey column never collides.
 */
final class AppFixtures extends Fixture
{
    private const INVOICE_COUNT = 30;
    private const PARTY_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function load(ObjectManager $manager): void
    {
        $currencies = ['EUR', 'USD', 'GBP', 'TND'];

        // Deterministic party identities, reused across the run.
        $namespace = Uuid::fromString(self::PARTY_NAMESPACE);
        $parties = [];
        foreach (['Acme Corp', 'Northwind Traders', 'Globex', 'Initech', 'Umbro AG'] as $index => $name) {
            $parties[] = ['id' => Uuid::v5($namespace, 'party-'.$index), 'name' => $name];
        }

        $taxRates = [0, 500, 1000, 2000]; // basis points: 0%, 5%, 10%, 20%

        for ($i = 1; $i <= self::INVOICE_COUNT; ++$i) {
            $type = 0 === $i % 2 ? InvoiceType::PURCHASE : InvoiceType::SALES;
            $currency = $currencies[$i % \count($currencies)];
            $party = $parties[$i % \count($parties)];

            // Fresh orderId per invoice keeps the unique activeOrderKey distinct.
            $invoice = new Invoice(
                Uuid::v7(),
                $type,
                $party['id'],
                $party['name'],
                $currency,
            );

            // At least one line for every invoice; every third one gets a second line.
            $lineCount = 0 === $i % 3 ? 2 : 1;
            for ($line = 1; $line <= $lineCount; ++$line) {
                $quantity = 1 + (($i + $line) % 4);
                $unitPrice = 1000 * ($i + $line); // minor units
                $taxRateBps = $taxRates[($i + $line) % \count($taxRates)];
                $taxAmount = intdiv($quantity * $unitPrice * $taxRateBps, 10000); // minor units

                $invoice->addLine(new InvoiceLine(
                    Uuid::v7(),
                    sprintf('Line %d for invoice %d', $line, $i),
                    $quantity,
                    $unitPrice,
                    $taxRateBps,
                    $taxAmount,
                ));
            }

            // Drive the domain state machine so all five statuses are represented.
            switch ($i % 5) {
                case 0: // DRAFT - leave the invoice exactly as constructed
                    break;
                case 1: // ISSUED
                    $invoice->issue($this->number($type, $i), new \DateTimeImmutable('+30 days'));
                    break;
                case 2: // PARTIALLY_PAID - issue, then pay half the balance
                    $invoice->issue($this->number($type, $i), new \DateTimeImmutable('+30 days'));
                    $invoice->applyPayment(Uuid::v7(), intdiv($invoice->getTotal(), 2), $currency);
                    break;
                case 3: // PAID - issue, then pay the full balance
                    $invoice->issue($this->number($type, $i), new \DateTimeImmutable('+30 days'));
                    $invoice->applyPayment(Uuid::v7(), $invoice->getTotal(), $currency);
                    break;
                case 4: // VOIDED - legal directly from DRAFT
                    $invoice->void('Voided by fixtures.');
                    break;
            }

            // Invoice cascades persist/remove to its lines and payments.
            $manager->persist($invoice);
        }

        // Single flush: cascade writes the 40 lines and the payment rows too.
        $manager->flush();
    }

    private function number(InvoiceType $type, int $index): string
    {
        return sprintf('%s-%04d', $type->numberPrefix(), $index);
    }
}
