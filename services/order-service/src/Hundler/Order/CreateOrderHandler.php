<?php

declare(strict_types=1);

namespace App\Hundler\Order;

use App\Api\V1\Request\CreateOrderLineRequest;
use App\Api\V1\Request\CreateOrderRequest;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Enum\OrderType;
use App\Event\OrderSubmittedV1;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Use case: create an order (draft).
 * The order and its outbox event are saved in ONE transaction.
 */
final readonly class CreateOrderHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(CreateOrderRequest $request): Order
    {
        $type = OrderType::from($request->type);

        $order = new Order(
            number: $request->number,
            type: $type,
            partyId: $request->partyId,
            partyName: $request->partyName,
            currency: $request->currency
        );

        if ($request->invoiceId !== null) {
            $order->attachInvoice(Uuid::fromString($request->invoiceId));
        }

        foreach ($request->lines as $lineRequest) {
            $line = new OrderLine();
            $line->setProductId(new Uuid($lineRequest->productId));
            $line->setProductName($lineRequest->productName);
            $line->setSku($lineRequest->sku);
            $line->setQuantity($lineRequest->quantity);
            $line->setUnitPrice($lineRequest->unitPrice);

            $order->addLine($line);
        }

        $this->em->wrapInTransaction(function () use ($order): void {
            $this->em->persist($order);
            $this->em->flush(); // generates the ids needed by the event payload
        });

        return $order;
    }
}