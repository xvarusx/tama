<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\OutboxEvent;

interface InvoiceDomainEvent
{
    public function toOutbox(): OutboxEvent;
}
