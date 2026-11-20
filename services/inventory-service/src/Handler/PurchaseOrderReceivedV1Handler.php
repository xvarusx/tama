<?php

namespace App\Handler;

use App\Event\PurchaseOrderReceivedV1 as PurchaseOrderReceivedV1Message;
use App\Repository\InventoryRepository;
use App\Service\StockMovementService;
use App\Utils\Decimal;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Consumes purchase.order.received events from Order Service.
 * Records incoming stock (IN movements) for received goods.
 */
#[AsMessageHandler]
final readonly class PurchaseOrderReceivedV1Handler
{
    public function __construct(
        private InventoryRepository $inventories,
        private StockMovementService $stock,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    public function __invoke(PurchaseOrderReceivedV1Message $message): void
    {
        foreach ($message->lineItems as $line) {
            $productId = $line['productId'];
            $quantity = $line['quantity'];

            if (Decimal::compare((string)$quantity, '0') <= 0) {
                continue;
            }

            $inventory = $this->inventories->findActiveByProductId($productId);

            if ($inventory === null) {
                // Strategy: Create inventory if it doesn't exist (auto-provisioning)
                // This allows purchase orders to create inventory automatically
                $inventory = $this->stock->createInventory(
                    $productId,
                    (string)$quantity,
                    '0', // Default reorder level
                );
                $this->cache->invalidateTags(['inventory']);

                continue;
            }

            try {
                $result = $this->stock->receiveStock(
                    $inventory->getId()->toRfc4122(),
                    (string)$quantity,
                    'order',
                    $message->orderId,
                    sprintf('Purchase order %s received', $message->orderNumber),
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
