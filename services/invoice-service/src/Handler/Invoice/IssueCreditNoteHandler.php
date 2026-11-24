<?php

declare(strict_types=1);

namespace App\Handler\Invoice;

use App\Entity\Invoice;
use App\Exceptions\InvoiceNotFoundException;
use App\Handler\Outbox\OutboxRecorder;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class IssueCreditNoteHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private OutboxRecorder $outbox,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Uuid $invoiceId, int $amount, string $reason): Invoice
    {
        $invoice = $this->invoices->findByUuid($invoiceId)
            ?? throw new InvoiceNotFoundException($invoiceId->toRfc4122());

        $invoice->issueCreditNote($amount, $reason);

        $this->em->wrapInTransaction(function () use ($invoice): void {
            $this->em->flush();
            $this->outbox->record($this->em, $invoice);
            $this->em->flush();
        });

        return $invoice;
    }
}
