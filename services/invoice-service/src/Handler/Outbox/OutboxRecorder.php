<?php

declare(strict_types=1);

namespace App\Handler\Outbox;

use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/** Persists the aggregate's pending domain events as outbox rows in the same transaction. */
final readonly class OutboxRecorder
{
    public function record(EntityManagerInterface $em, Invoice $invoice): void
    {
        foreach ($invoice->pullEvents() as $event) {
            $em->persist($event->toOutbox());
        }
    }
}
