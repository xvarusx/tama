<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\PaymentResult;
use App\Handler\Invoice\ApplyPaymentHandler;
use App\Message\PaymentReceivedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class PaymentReceivedMessageHandler
{
    public function __construct(private ApplyPaymentHandler $applyPayment) {}

    public function __invoke(PaymentReceivedMessage $message): PaymentResult
    {
        return ($this->applyPayment)(
            Uuid::fromString($message->invoiceId),
            Uuid::fromString($message->paymentId),
            $message->amount,
            $message->currency,
        );
    }
}
