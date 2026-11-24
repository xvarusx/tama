<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Delivery;

/** Boundary around the email provider, so the worker can be tested offline. */
interface DeliverySender
{
    /**
     * @throws DeliverySendException when the provider rejects or fails the message
     */
    public function send(Delivery $delivery, string $fromAddress, string $fromName): string;
}
