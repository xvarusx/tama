<?php

namespace App\Handler;

use App\Event\OrderShippedV1 as OrderShippedV1Message;
use App\Repository\InventoryRepository;
use App\Service\StockMovementService;
use App\Utils\Decimal;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Consumes order.shipped events from Order Service.
 * Records outgoing stock (OUT movements) for shipped items.
 */
#[AsMessageHandler]
final readonly class OrderShippedV1Handler
{
    public function __construct(
        private InventoryRepository $inventories,
        private StockMovementService $stock,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    public function __invoke(OrderShippedV1Message $message): void
    {
        foreach ($message->lineItems as $line) {
            $productId = $line['productId'];
            $quantity = $line['quantity'];

            if (Decimal::compare((string)$quantity, '0') <= 0) {
                continue;
            }

            $inventory = $this->inventories->findActiveByProductId($productId);

            if ($inventory === null) {
                // Strategy: No inventory means no stock to ship
                // Log or throw depending on business rules
                throw new UnrecoverableMessageHandlingException(
                    sprintf('Inventory not found for product %s (order %s shipped)', $productId, $message->orderId)
                );
            }

            try {
                $result = $this->stock->issueStock(
                    $inventory->getId()->toRfc4122(),
                    (string)$quantity,
                    'order',
                    $message->orderId,
                    sprintf('Order %s shipped', $message->orderNumber),
                );

                if ($result->created) {
                    $this->cache->invalidateTags(['inventory']);
                }
            } catch (ConflictHttpException|UnprocessableEntityHttpException $e) {
                throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
            }
        }
    }
}
