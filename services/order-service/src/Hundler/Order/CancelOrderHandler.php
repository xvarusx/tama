<?php

declare(strict_types=1);

namespace App\Hundler\Order;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Use case: cancel an order.
 * Can be done from any state except CANCELED.
 */
final readonly class CancelOrderHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Order $order): Order
    {
        $this->em->wrapInTransaction(function () use ($order): void {
            $order->cancel();
            $this->em->persist($order);
            $this->em->flush();
        });

        return $order;
    }
}