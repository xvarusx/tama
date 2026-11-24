<?php

declare(strict_types=1);

namespace App\Handler\Invoice;

use App\Entity\InvoiceReference;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handle invoice.voided event from Invoice Service.
 * Updates the invoice reference status and marks related payments as voided.
 */
final readonly class HandleInvoiceVoidedV1
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(array $payload): void
    {
        $invoiceRef = $this->em->find(InvoiceReference::class, $payload['invoiceId']);

        if ($invoiceRef !== null) {
            $invoiceRef->refreshFrom($payload);
            
            // Update status to VOIDED
            $invoiceRef->setStatus('VOIDED');

            $this->em->wrapInTransaction(function () use ($invoiceRef): void {
                $this->em->persist($invoiceRef);
                $this->em->flush();
            });
        }
    }
}