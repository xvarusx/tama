<?php

declare(strict_types=1);

namespace App\Handler\Invoice;

use App\Dto\InvoiceLineInput;
use App\Dto\UpdateInvoiceInput;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Enum\InvoiceStatus;
use App\Exceptions\InvoiceNotEditableException;
use App\Exceptions\InvoiceNotFoundException;
use App\Exceptions\TaxServiceUnavailableException;
use App\Repository\InvoiceRepository;
use App\Tax\TaxCalculatorInterface;
use App\Tax\TaxLineInput;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateDraftInvoiceHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private TaxCalculatorInterface $tax,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Uuid $invoiceId, UpdateInvoiceInput $input): Invoice
    {
        $invoice = $this->invoices->findByUuid($invoiceId)
            ?? throw new InvoiceNotFoundException($invoiceId->toRfc4122());

        if ($invoice->getStatus() !== InvoiceStatus::DRAFT) {
            throw new InvoiceNotEditableException($invoice->getStatus()->value);
        }
        if ($input->lines === []) {
            throw new InvalidArgumentException('An invoice needs at least one line.');
        }

        $currency = $input->currency ?? $invoice->getCurrency();
        $lines = $this->buildLines($input->lines, $currency);

        $this->em->wrapInTransaction(function () use ($invoice, $input, $lines): void {
            if ($input->partyName !== null) {
                $invoice->setPartyName($input->partyName);
            }
            if ($input->currency !== null) {
                $invoice->setCurrency($input->currency);
            }

            $invoice->replaceLines($lines);
            $this->em->flush();
        });

        return $invoice;
    }

    /**
     * @param list<InvoiceLineInput> $inputs
     *
     * @return list<InvoiceLine>
     */
    private function buildLines(array $inputs, string $currency): array
    {
        $taxInputs = array_map(
            static fn(InvoiceLineInput $line): TaxLineInput => new TaxLineInput(
                $line->productId,
                $line->quantity,
                $line->unitPrice,
            ),
            $inputs,
        );

        $taxResults = $this->tax->calculate($currency, $taxInputs);
        if (\count($taxResults) !== \count($inputs)) {
            throw new TaxServiceUnavailableException('The Tax Service did not return one result per line.');
        }

        $lines = [];
        foreach ($inputs as $index => $line) {
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
