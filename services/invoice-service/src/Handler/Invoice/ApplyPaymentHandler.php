<?php

declare(strict_types=1);

namespace App\Handler\Invoice;

use App\Enum\PaymentResult;
use App\Exceptions\InvoiceNotFoundException;
use App\Exceptions\PaymentIdConflictException;
use App\Handler\Outbox\OutboxRecorder;
use App\Repository\InvoicePaymentRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ApplyPaymentHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoicePaymentRepository $payments,
        private OutboxRecorder $outbox,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Uuid $invoiceId, Uuid $paymentId, int $amount, string $currency): PaymentResult
    {
        $invoice = $this->invoices->findByUuid($invoiceId)
            ?? throw new InvoiceNotFoundException($invoiceId->toRfc4122());

        $existing = $this->payments->findByPaymentId($paymentId);
        if ($existing !== null) {
            if ($existing->getInvoice()->getId()->equals($invoice->getId())) {
                return PaymentResult::DUPLICATE;
            }

            throw new PaymentIdConflictException($paymentId->toRfc4122());
        }

        $result = $invoice->applyPayment($paymentId, $amount, $currency);

        if ($result === PaymentResult::DUPLICATE) {
            return $result;
        }

        $this->em->wrapInTransaction(function () use ($invoice): void {
            $this->em->flush();
            $this->outbox->record($this->em, $invoice);
            $this->em->flush();
        });

        return $result;
    }
}
