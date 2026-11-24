<?php

declare(strict_types=1);

namespace App\Handler\Invoice;

use App\Entity\InvoiceReference;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handle invoice.paid event from Invoice Service.
 * Updates the invoice reference status and outstanding amount.
 */
final readonly class HandleInvoicePaidV1
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(array $payload): void
    {
        $invoiceRef = $this->em->find(InvoiceReference::class, $payload['invoiceId']);

        if ($invoiceRef !== null) {
            $invoiceRef->refreshFrom($payload);
            
            // Update outstanding to 0 for a paid invoice
            $invoiceRef->setOutstanding(0);
            $invoiceRef->setStatus('PAID');

            $this->em->wrapInTransaction(function () use ($invoiceRef): void {
                $this->em->persist($invoiceRef);
                $this->em->flush();
            });
        }
    }
}