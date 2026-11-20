<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Delivery;
use App\Mail\DeliverySender;
use App\Mail\DeliverySendException;

/** Test double for the mail provider: records calls and fails on demand. */
final class FakeDeliverySender implements DeliverySender
{
    /** @var list<array{email: string, subject: string, body: string, fromAddress: string, fromName: string}> */
    public array $sent = [];

    private ?DeliverySendException $failure = null;

    public function setFailure(?DeliverySendException $failure): void
    {
        $this->failure = $failure;
    }

    public function send(Delivery $delivery, string $fromAddress, string $fromName): string
    {
        $this->sent[] = [
            'email' => $delivery->getRecipientEmail(),
            'subject' => $delivery->getSubject(),
            'body' => $delivery->getBody(),
            'fromAddress' => $fromAddress,
            'fromName' => $fromName,
        ];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return 'provider-msg-' . \count($this->sent);
    }
}
