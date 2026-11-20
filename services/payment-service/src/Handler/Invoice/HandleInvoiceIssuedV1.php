<?php

declare(strict_types=1);

namespace App\Handler\Invoice;

use App\Entity\InvoiceReference;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handle invoice.issued event from Invoice Service.
 * Creates or updates the invoice reference read model.
 */
final readonly class HandleInvoiceIssuedV1
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(array $payload): void
    {
        $invoiceRef = $this->em->find(InvoiceReference::class, $payload['invoiceId']);

        if ($invoiceRef === null) {
            $invoiceRef = new InvoiceReference(
                invoiceId: $payload['invoiceId'],
                number: $payload['number'],
                type: $payload['type'],
                partyId: $payload['partyId'],
                partyName: $payload['partyName'],
                currency: $payload['currency'],
                total: $payload['total'],
                outstanding: $payload['outstanding'],
                status: $payload['status'],
                createdAt: isset($payload['createdAt']) ? new \DateTimeImmutable($payload['createdAt']) : new \DateTimeImmutable(),
                updatedAt: new \DateTimeImmutable(),
            );
            $this->em->persist($invoiceRef);
        } else {
            $invoiceRef->refreshFrom($payload);
        }

        $this->em->wrapInTransaction(function () use ($invoiceRef): void {
            $this->em->flush();
        });
    }
}