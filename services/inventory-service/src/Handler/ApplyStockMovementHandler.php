<?php

namespace App\Handler;

use App\Enum\InventoryMovementType;
use App\Event\ApplyStockMovement;
use App\Repository\InventoryRepository;
use App\Service\StockMovementService;
use App\Utils\Decimal;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsMessageHandler]
final readonly class ApplyStockMovementHandler
{
    public function __construct(
        private InventoryRepository $inventories,
        private StockMovementService $stock,
        #[Autowire(service: 'cache.inventory')]
        private TagAwareCacheInterface $cache,
    ) {}

    public function __invoke(ApplyStockMovement $m): void
    {
        $inventory = $this->inventories->findActiveByProductId($m->productId)
            ?? throw new UnrecoverableMessageHandlingException('Unknown product');

        try {
            $result = $this->stock->apply(
                $inventory->getId()->toRfc4122(),
                InventoryMovementType::from($m->type),
                Decimal::format($m->quantity),
                $m->referenceType,
                $m->referenceId,
                $m->reason,
            );
        } catch (ConflictHttpException|UnprocessableEntityHttpException $e) {
            // Business rejection (e.g. insufficient stock): retrying won't help
            throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
        }

        if ($result->created) {
            $this->cache->invalidateTags(['inventory']);
        }
    }
}
