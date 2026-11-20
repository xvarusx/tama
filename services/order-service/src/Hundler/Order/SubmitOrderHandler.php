<?php

declare(strict_types=1);

namespace App\Hundler\Order;

use App\Entity\Order;
use App\Event\OrderSubmittedV1;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Use case: submit an order.
 * The order and its outbox event are saved in ONE transaction.
 */
final readonly class SubmitOrderHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Order $order): Order
    {
        $this->em->wrapInTransaction(function () use ($order): void {
            $order->submit();
            $this->em->persist($order);
            $this->em->flush();

            $this->em->persist(OrderSubmittedV1::create($order)->toOutbox());
            $this->em->flush();
        });

        return $order;
    }
}