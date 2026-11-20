<?php

declare(strict_types=1);

namespace App\Handler\Invoice;

use App\Entity\Invoice;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Exceptions\InvoiceNotFoundException;
use App\Handler\Outbox\OutboxRecorder;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceNumberGenerator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class IssueInvoiceHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceNumberGenerator $numbers,
        private OutboxRecorder $outbox,
        private EntityManagerInterface $em,
        #[Autowire(param: 'invoice.payment_term_days')]
        private int $paymentTermDays,
    ) {}

    public function __invoke(Uuid $invoiceId, ?DateTimeImmutable $dueDate = null): Invoice
    {
        $invoice = $this->invoices->findByUuid($invoiceId)
            ?? throw new InvoiceNotFoundException($invoiceId->toRfc4122());

        $issueDate = new DateTimeImmutable('today');
        $dueDate ??= $issueDate->modify(sprintf('+%d days', $this->paymentTermDays));

        if ($dueDate < $issueDate) {
            throw new InvalidInvoiceTransitionException('The due date must not be before the issue date.');
        }

        $this->numbers->locked($invoice->getType(), $issueDate, function (string $number) use ($invoice, $dueDate): void {
            $invoice->issue($number, $dueDate);

            $this->em->wrapInTransaction(function () use ($invoice): void {
                $this->em->flush();
                $this->outbox->record($this->em, $invoice);
                $this->em->flush();
            });
        });

        return $invoice;
    }
}
