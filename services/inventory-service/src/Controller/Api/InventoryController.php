<?php

namespace App\Controller\Api;

use App\Dto\CreateInventoryInput;
use App\Dto\CreateInventoryMovementInput;
use App\Dto\UpdateInventoryInput;
use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventoryRepository;
use App\Service\StockMovementService;
use App\Util\Decimal;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class InventoryController extends AbstractController
{
    private const array READ_CONTEXT = ['groups' => ['inventory:read']];
    private const array DECIMAL_FIELDS = ['quantityOnHand', 'quantityReserved', 'reorderLevel', 'quantity', 'quantityDelta', 'balanceAfter', 'reservedDelta', 'reservedAfter', 'availableQuantity'];
    private const int CACHE_TTL = 60;
    private const string CACHE_TAG = 'inventory';

    public function __construct(
        #[Autowire(service: 'cache.inventory')]
        private readonly TagAwareCacheInterface $cache,
        private readonly InventoryRepository $inventories,
        private readonly InventoryMovementRepository $movements,
        private readonly StockMovementService $stock,
        private readonly SerializerInterface $serializer,
    ) {
    }


    #[Route('/api/inventory', name: 'app_api_inventory_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 10)));

        return $this->cachedList(
            sprintf('inventory_page_%d_limit_%d', $page, $limit),
            fn (): array => $this->inventories->findActivePage($limit, ($page - 1) * $limit),
        );
    }

    #[Route('/api/inventory/low-stock', name: 'app_api_inventory_low_stock', methods: ['GET'])]
    public function lowStock(): JsonResponse
    {
        return $this->cachedList('inventory_low_stock', fn (): array => $this->inventories->findLowStock());
    }

    #[Route('/api/inventory/out-of-stock', name: 'app_api_inventory_out_of_stock', methods: ['GET'])]
    public function outOfStock(): JsonResponse
    {
        return $this->cachedList('inventory_out_of_stock', fn (): array => $this->inventories->findOutOfStock());
    }

    #[Route('/api/inventory/product/{productId<\d+>}', name: 'app_api_inventory_show_by_product', methods: ['GET'])]
    public function showByProductId(int $productId): JsonResponse
    {
        $inventory = $this->inventories->findActiveByProductId($productId)
            ?? throw $this->createNotFoundException('Inventory item not found');

        return $this->jsonResource($inventory);
    }

    #[Route('/api/inventory/{id<\d+>}', name: 'app_api_inventory_show', methods: ['GET'])]
    public function showById(int $id): JsonResponse
    {
        $inventory = $this->inventories->findActive($id)
            ?? throw $this->createNotFoundException('Inventory item not found');

        return $this->jsonResource($inventory);
    }

    #[Route('/api/inventory/{id<\d+>}/movements', name: 'app_api_inventory_movements_index', methods: ['GET'])]
    public function movements(Request $request, int $id): JsonResponse
    {
        // History is intentionally available for soft-deleted items too.
        $inventory = $this->inventories->find($id)
            ?? throw $this->createNotFoundException('Inventory item not found');

        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        $items = $this->movements->findBy(
            ['inventory' => $inventory],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
            $limit,
            ($page - 1) * $limit,
        );

        $response = $this->jsonResource($items);
        $response->headers->set('X-Total-Count', (string) $this->movements->count(['inventory' => $inventory]));

        return $response;
    }

    #[Route('/api/inventory/{id<\d+>}/movements', name: 'app_api_inventory_movements_create', methods: ['POST'])]
    public function createMovement(
        #[MapEntity(expr: 'repository.findActive(id)')] Inventory $inventory,
        #[MapRequestPayload] CreateInventoryMovementInput $input,
    ): JsonResponse
    {
        $result = $this->stock->apply(
            $inventory->getId(),
            $input->type,
            Decimal::format($input->quantity),
            $input->referenceType,
            $input->referenceId,
            $input->reason,
        );

        if (!$result->created) {
            return $this->jsonResource($result->movement, Response::HTTP_OK);
        }

        $this->cache->invalidateTags([self::CACHE_TAG]);

        return $this->jsonResource($result->movement, Response::HTTP_CREATED);
    }

    #[Route('/api/inventory', name: 'app_api_inventory_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateInventoryInput $input): JsonResponse
    {
        try {
            $inventory = $this->stock->createInventory(
                $input->productId,
                Decimal::format($input->quantityOnHand ?? 0),
                Decimal::format($input->reorderLevel ?? 0),
            );
        } catch (UniqueConstraintViolationException) {
            return $this->json(['error' => 'Inventory already exists for this product.'], Response::HTTP_CONFLICT);
        }

        $this->cache->invalidateTags([self::CACHE_TAG]);

        return $this->jsonResource($inventory, Response::HTTP_CREATED);
    }

    #[Route('/api/inventory/{id<\d+>}', name: 'app_api_inventory_update', methods: ['PATCH'])]
    public function update(int $id, #[MapRequestPayload] UpdateInventoryInput $input): JsonResponse
    {
        try {
            $inventory = $this->stock->updateSettings(
                $id,
                Decimal::format($input->reorderLevel),
                $input->version,
            );
        } catch (OptimisticLockException) {
            return $this->json(['error' => 'The inventory item was modified by another request.'], Response::HTTP_CONFLICT);
        }

        $this->cache->invalidateTags([self::CACHE_TAG]);

        return $this->jsonResource($inventory);
    }

    #[Route('/api/inventory/{id<\d+>}', name: 'app_api_inventory_delete', methods: ['DELETE'])]
    public function delete(#[MapEntity(expr: 'repository.findActive(id)')] Inventory $inventory): Response
    {
        $this->stock->softDelete($inventory);
        $this->cache->invalidateTags([self::CACHE_TAG]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function cachedList(string $key, callable $loader): JsonResponse
    {
        $json = $this->cache->get($key, function (ItemInterface $item) use ($loader): string {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag([self::CACHE_TAG]);

            return $this->serialize($loader());
        });

        return JsonResponse::fromJsonString($json);
    }

    private function jsonResource(Inventory|InventoryMovement|array $resource, int $status = Response::HTTP_OK): JsonResponse
    {
        return JsonResponse::fromJsonString($this->serialize($resource), $status);
    }

    private function serialize(Inventory|InventoryMovement|array $resource): string
    {
        $data = $this->serializer->normalize($resource, 'json', self::READ_CONTEXT);

        return json_encode($this->normalizeDecimalFields($data), JSON_THROW_ON_ERROR);
    }

    private function normalizeDecimalFields(mixed $data): mixed
    {
        if (!\is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->normalizeDecimalFields($value);
            } elseif (null !== $value && \in_array($key, self::DECIMAL_FIELDS, true)) {
                $data[$key] = Decimal::format($value);
            }
        }

        return $data;
    }
}
