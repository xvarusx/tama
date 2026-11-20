<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Dto\InvoiceDraftInput;
use App\Dto\InvoiceLineInput;
use App\Entity\Invoice;
use App\Enum\InvoiceType;
use App\Handler\Invoice\CreateDraftInvoiceHandler;
use App\Message\OrderReadyForInvoiceMessage;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class OrderReadyForInvoiceMessageHandler
{
    private const array INVOICEABLE_EVENTS = ['order.shipped', 'purchase.order.received'];

    public function __construct(private CreateDraftInvoiceHandler $createDraftInvoice) {}

    public function __invoke(OrderReadyForInvoiceMessage $message): ?Invoice
    {
        if (!\in_array($message->eventType, self::INVOICEABLE_EVENTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported order event type "%s"; expected one of: %s.',
                $message->eventType,
                implode(', ', self::INVOICEABLE_EVENTS),
            ));
        }

        $type = InvoiceType::from($message->type);

        $lines = array_map(
            static fn(array $line): InvoiceLineInput => new InvoiceLineInput(
                Uuid::fromString($line['productId']),
                $line['description'],
                $line['quantity'],
                $line['unitPrice'],
            ),
            $message->lines,
        );

        return ($this->createDraftInvoice)(
            new InvoiceDraftInput(
                Uuid::fromString($message->orderId),
                $type,
                Uuid::fromString($message->partyId),
                $message->partyName,
                $message->currency,
                $lines,
            ),
            ignoreExisting: true,
        );
    }
}
