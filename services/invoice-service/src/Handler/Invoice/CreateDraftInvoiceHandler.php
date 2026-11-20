<?php

declare(strict_types=1);

namespace App\Handler\Invoice;

use App\Dto\InvoiceDraftInput;
use App\Dto\InvoiceLineInput;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Exceptions\InvoiceAlreadyExistsForOrderException;
use App\Exceptions\TaxServiceUnavailableException;
use App\Handler\Outbox\OutboxRecorder;
use App\Repository\InvoiceRepository;
use App\Tax\TaxCalculatorInterface;
use App\Tax\TaxLineInput;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use LogicException;

final readonly class CreateDraftInvoiceHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private TaxCalculatorInterface $tax,
        private OutboxRecorder $outbox,
        private ManagerRegistry $registry,
    ) {}

    public function __invoke(InvoiceDraftInput $input, bool $ignoreExisting = false): ?Invoice
    {
        if ($input->lines === []) {
            throw new InvalidArgumentException('An invoice needs at least one line.');
        }

        $existing = $this->invoices->findActiveByOrderId($input->orderId);
        if ($existing !== null) {
            if ($ignoreExisting) {
                return null;
            }

            throw new InvoiceAlreadyExistsForOrderException($input->orderId->toRfc4122());
        }

        $invoice = new Invoice(
            $input->orderId,
            $input->type,
            $input->partyId,
            $input->partyName,
            $input->currency,
        );

        foreach ($this->buildLines($input) as $line) {
            $invoice->addLine($line);
        }

        $em = $this->entityManager();

        try {
            $em->wrapInTransaction(function () use ($em, $invoice): void {
                $em->persist($invoice);
                $em->flush();
                $this->outbox->record($em, $invoice);
                $em->flush();
            });
        } catch (UniqueConstraintViolationException) {
            // The competing transaction won the race and already owns the
            // active invoice for this order. A failed flush closes the
            // manager, so reset it to keep the worker usable.
            $this->registry->resetManager();

            if ($ignoreExisting) {
                return null;
            }

            throw new InvoiceAlreadyExistsForOrderException($input->orderId->toRfc4122());
        }

        return $invoice;
    }

    private function entityManager(): EntityManagerInterface
    {
        $manager = $this->registry->getManager();
        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException('The configured Doctrine manager is not an ORM entity manager.');
        }

        return $manager;
    }

    /** @return list<InvoiceLine> */
    private function buildLines(InvoiceDraftInput $input): array
    {
        $taxInputs = array_map(
            static fn(InvoiceLineInput $line): TaxLineInput => new TaxLineInput(
                $line->productId,
                $line->quantity,
                $line->unitPrice,
            ),
            $input->lines,
        );

        $taxResults = $this->tax->calculate($input->currency, $taxInputs);
        if (\count($taxResults) !== \count($input->lines)) {
            throw new TaxServiceUnavailableException('The Tax Service did not return one result per line.');
        }

        $lines = [];
        foreach ($input->lines as $index => $line) {
            $lines[] = new InvoiceLine(
                $line->productId,
                $line->description,
                $line->quantity,
                $line->unitPrice,
                $taxResults[$index]->taxRateBps,
                $taxResults[$index]->taxAmount,
            );
        }

        return $lines;
    }
}
