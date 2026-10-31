<?php

namespace App\MessageHandler;

use App\Entity\Inventory;
use App\Entity\Product;
use App\Message\ProductUpdated;
use App\Util\Decimal;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Keeps the local Product projection in sync with the Product service.
 * Every event carries the full product state, so the handler is an idempotent upsert.
 */
#[AsMessageHandler]
final readonly class ProductUpdatedHandler
{
    private const string ACTIVE = 'active';
    private const string ZERO = '0.000';

    public function __construct(
        private ManagerRegistry $registry,
        #[Autowire(service: 'cache.inventory')]
        private TagAwareCacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProductUpdated $message): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->registry->getManager();

        try {
            $changed = $em->wrapInTransaction(fn (): bool => $this->apply($em, $message));
        } catch (UniqueConstraintViolationException $e) {
            // Another delivery inserted this product first. Our EM is closed:
            // reset it and rethrow so Messenger retries; the retry will find the row.
            $this->registry->resetManager();

            throw $e;
        }

        if ($changed) {
            $this->cache->invalidateTags(['inventory']);
        }
    }

    private function apply(EntityManagerInterface $em, ProductUpdated $message): bool
    {
        $product = $em->find(Product::class, $message->productId, LockMode::PESSIMISTIC_WRITE);

        if (null === $product) {
            $product = (new Product())->setId($message->productId);
            $em->persist($product);
        }

        $changed = $product->getSku() !== $message->sku
            || $product->getName() !== $message->name
            || $product->getStatus() !== $message->status;

        $product
            ->setSku($message->sku)
            ->setName($message->name)
            ->setStatus($message->status);

        $inventory = $em->getRepository(Inventory::class)->findOneBy(['productId' => $message->productId]);

        if (null === $inventory && self::ACTIVE === $message->status) {
            $now = new \DateTimeImmutable();
            $inventory = (new Inventory())
                ->setProductId($message->productId)
                ->setQuantityOnHand(self::ZERO)
                ->setQuantityReserved(self::ZERO)
                ->setReorderLevel(self::ZERO)
                ->setCreatedAt($now)
                ->setUpdatedAt($now);
            $em->persist($inventory);
            $changed = true;
        } elseif (null !== $inventory) {
            $this->syncInventory($inventory, self::ACTIVE === $message->status);
        }

        return $changed;
    }

    private function syncInventory(Inventory $inventory, bool $productIsActive): void
    {
        $isDeleted = null !== $inventory->getDeletedAt();
        $now = new \DateTimeImmutable();

        if ($productIsActive && $isDeleted) {
            // Product reactivated: bring the (empty) inventory row back.
            $inventory->setDeletedAt(null)->setUpdatedAt($now);

            return;
        }

        if (!$productIsActive && !$isDeleted) {
            $hasStock = 0 !== Decimal::compare($inventory->getQuantityOnHand() ?? self::ZERO, self::ZERO)
                || 0 !== Decimal::compare($inventory->getQuantityReserved() ?? self::ZERO, self::ZERO);

            if ($hasStock) {
                // Never discard stock silently: keep the row and raise it for a human.
                $this->logger->warning('Product archived while inventory still has stock.', [
                    'inventoryId' => $inventory->getId(),
                    'onHand' => $inventory->getQuantityOnHand(),
                    'reserved' => $inventory->getQuantityReserved(),
                ]);

                return;
            }

            $inventory->setDeletedAt($now)->setUpdatedAt($now);
        }
    }
}
