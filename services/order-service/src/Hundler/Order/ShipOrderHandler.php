<?php

declare(strict_types=1);

namespace App\Hundler\Order;

use App\Api\V1\Request\ShipOrderRequest;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Use case: ship an order.
 * Only applicable to sales orders that have been received.
 */
final readonly class ShipOrderHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Order $order, ShipOrderRequest $request): Order
    {
        $this->em->wrapInTransaction(function () use ($order, $request): void {
            $order->ship($request->quantities);
            $this->em->persist($order);
            $this->em->flush();
        });

        return $order;
    }
}