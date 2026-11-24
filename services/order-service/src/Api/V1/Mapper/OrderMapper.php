<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\OrderResource;
use App\Entity\Order;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class OrderMapper
{
    public function toResource(Order $order): OrderResource
    {
        $lines = [];
        foreach ($order->getLines() as $line) {
            $lines[] = [
                'id' => $line->getId()->toRfc4122(),
                'productId' => (string) $line->getProductId(),
                'productName' => $line->getProductName(),
                'sku' => $line->getSku(),
                'quantity' => $line->getQuantity(),
                'quantityProcessed' => $line->getQuantityProcessed(),
                'unitPrice' => $line->getUnitPrice(),
                'subtotal' => $line->getSubtotal(),
            ];
        }

        return new OrderResource(
            id: $order->getId()->toRfc4122(),
            attributes: [
                'number' => $order->getNumber(),
                'type' => $order->getType()->value,
                'status' => $order->getStatus()->value,
                'partyId' => $order->getPartyId(),
                'partyName' => $order->getPartyName(),
                'currency' => $order->getCurrency(),
                'totalAmount' => $order->getTotalAmount(),
                'totalProcessed' => $order->getTotalProcessed(),
                'invoiceId' => $order->getInvoiceId()?->toString(),
                'version' => $order->getVersion(),
                'lines' => $lines,
                'createdAt' => $order->getCreatedAt()?->format(\DATE_ATOM),
                'submittedAt' => $order->getSubmittedAt()?->format(\DATE_ATOM),
            ],
        );
    }
}