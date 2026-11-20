<?php

declare(strict_types=1);

namespace App\Hundler\Order;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Use case: mark a sales order as delivered.
 */
final readonly class MarkDeliveredHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Order $order): Order
    {
        $this->em->wrapInTransaction(function () use ($order): void {
            $order->markDelivered();
            $this->em->persist($order);
            $this->em->flush();
        });

        return $order;
    }
}