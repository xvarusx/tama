<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Contract v1 of invoice.issued - an inbound event from Invoice Service.
 * Received when a new invoice is created.
 */
final class InvoiceIssuedV1
{
    public const string TYPE = 'invoice.issued';
    public const int VERSION = 1;
}