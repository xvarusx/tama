<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Delivery;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Throwable;

/** Sends a delivery using only the content snapshotted on the outbox row. */
final readonly class EmailDeliverySender implements DeliverySender
{
    public function __construct(private MailerInterface $mailer) {}

    public function send(Delivery $delivery, string $fromAddress, string $fromName): string
    {
        try {
            $email = new Email()
                ->from(new Address($fromAddress, $fromName))
                ->to($delivery->getRecipientEmail())
                ->subject($delivery->getSubject())
                ->html($delivery->getBody());
            $this->mailer->send($email);
        } catch (Throwable $exception) {
            throw DeliverySendException::fromThrowable($exception);
        }

        // Symfony's MailerInterface::send() is void; the transport adds the
        // Message-ID header while preparing the message. Fall back to a UUID
        // when a transport does not expose one.
        $messageId = $email->getHeaders()->get('Message-ID');

        return $messageId?->getBodyAsString() ?? (string)Uuid::v7();
    }
}
