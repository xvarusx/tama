<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Contract v1 of invoice.paid - an inbound event from Invoice Service.
 * Received when an invoice is marked as paid.
 */
final class InvoicePaidV1
{
    public const string TYPE = 'invoice.paid';
    public const int VERSION = 1;
}