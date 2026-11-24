<?php

declare(strict_types=1);

namespace App\Hundler\Order;

use App\Api\V1\Request\CreateOrderLineRequest;
use App\Api\V1\Request\UpdateOrderRequest;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Event\OrderUpdatedV1;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Use case: update an order's lines.
 * Can only be done on draft orders.
 */
final readonly class UpdateOrderHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Order $order, UpdateOrderRequest $request): Order
    {
        $order->removeAllLines();

        foreach ($request->lines as $lineRequest) {
            $line = new OrderLine();
            $line->setProductId(new \Symfony\Component\Uid\Uuid($lineRequest->productId));
            $line->setProductName($lineRequest->productName);
            $line->setSku($lineRequest->sku);
            $line->setQuantity($lineRequest->quantity);
            $line->setUnitPrice($lineRequest->unitPrice);

            $order->addLine($line);
        }

        $this->em->wrapInTransaction(function () use ($order): void {
            $this->em->persist($order);
            $this->em->flush();

            $this->em->persist(OrderUpdatedV1::create($order)->toOutbox());
            $this->em->flush();
        });

        return $order;
    }
}