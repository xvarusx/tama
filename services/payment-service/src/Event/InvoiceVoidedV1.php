<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Contract v1 of invoice.voided - an inbound event from Invoice Service.
 * Received when an invoice is voided.
 */
final class InvoiceVoidedV1
{
    public const string TYPE = 'invoice.voided';
    public const int VERSION = 1;
}