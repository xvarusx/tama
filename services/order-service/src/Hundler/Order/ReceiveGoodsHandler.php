<?php

declare(strict_types=1);

namespace App\Hundler\Order;

use App\Api\V1\Request\ReceiveGoodsRequest;
use App\Entity\Order;
use App\Enum\OrderType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Use case: receive goods for a purchase order.
 * Can be repeated until all lines are processed.
 */
final readonly class ReceiveGoodsHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Order $order, ReceiveGoodsRequest $request): Order
    {
        $this->em->wrapInTransaction(function () use ($order, $request): void {
            if ($order->getType() === OrderType::SALES) {
                $order->receive($request->quantities);
            } else {
                $order->receive($request->quantities);
            }
            $this->em->persist($order);
            $this->em->flush();
        });

        return $order;
    }
}