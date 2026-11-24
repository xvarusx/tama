<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\InventoryDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\InventoryMapper;
use App\Api\V1\Request\CreateInventoryMovementRequest;
use App\Api\V1\Request\CreateInventoryRequest;
use App\Api\V1\Request\UpdateInventoryRequest;
use App\Entity\Inventory;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventoryRepository;
use App\Service\StockMovementService;
use App\Utils\ApiResponse;
use App\Utils\Decimal;
use App\Utils\Pagination;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('', name: 'api_v1_')]
#[OA\Tag(name: 'Inventory', description: 'Inventory management endpoints')]
#[OA\Tag(name: 'Movements', description: 'Stock movement tracking endpoints')]
final class InventoryController extends AbstractController
{
    private const string CACHE_TAG = 'inventory';

    public function __construct(
        #[Autowire(service: 'cache.inventory')]
        private readonly TagAwareCacheInterface $cache,
        private readonly InventoryRepository $inventories,
        private readonly InventoryMovementRepository $movements,
        private readonly StockMovementService $stock,
        private readonly InventoryMapper $mapper,
    ) {}

    #[Route('/inventories', name: 'inventory_index', methods: ['GET'])]
    #[OA\Get(
        operationId: 'listInventories',
        tags: ['Inventory'],
        summary: 'List all inventory items',
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Successful response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: InventoryDocument::class))),
                        new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 10)));
        $key = sprintf('inventory_index_%d_%d', $page, $limit);

        $cached = $this->cacheGet($key, fn(): array => [
            'data' => array_map($this->mapper->toResource(...), $this->inventories->findActivePage($limit, ($page - 1) * $limit)),
            'total' => $this->inventories->countActive(),
        ]);

        $pagination = Pagination::create(
            $page,
            $limit,
            (int)$cached['total'],
            fn(int $pageNumber): string => $this->generateUrl('api_v1_inventory_index', ['page' => $pageNumber, 'limit' => $limit]),
        );

        return new JsonResponse(ApiResponse::success($cached['data'], $pagination));
    }

    #[Route('/inventories/low-stock', name: 'inventory_low_stock', methods: ['GET'])]
    #[OA\Get(
        operationId: 'listLowStock',
        tags: ['Inventory'],
        summary: 'List inventory items below reorder level',
    )]
    public function lowStock(): JsonResponse
    {
        $cached = $this->cacheGet('inventory_low_stock', fn(): array => array_map($this->mapper->toResource(...), $this->inventories->findLowStock()));

        return new JsonResponse(ApiResponse::success($cached));
    }

    #[Route('/inventories/out-of-stock', name: 'inventory_out_of_stock', methods: ['GET'])]
    #[OA\Get(
        operationId: 'listOutOfStock',
        tags: ['Inventory'],
        summary: 'List inventory items with zero stock',
    )]
    public function outOfStock(): JsonResponse
    {
        $cached = $this->cacheGet('inventory_out_of_stock', fn(): array => array_map($this->mapper->toResource(...), $this->inventories->findOutOfStock()));

        return new JsonResponse(ApiResponse::success($cached));
    }

    #[Route('/inventories/product/{productId}', name: 'inventory_show_by_product', methods: ['GET'])]
    #[OA\Get(
        operationId: 'getInventoryByProduct',
        tags: ['Inventory'],
        summary: 'Get inventory by product ID',
    )]
    #[OA\Parameter(name: 'productId', in: 'path', required: true, description: 'Product UUID')]
    public function showByProductId(string $productId): JsonResponse
    {
        $inventory = $this->cacheGet(
            sprintf('inventory_item_product_%s', $productId),
            fn(): ?Inventory => $this->inventories->findActiveByProductId($productId),
        );

        if ($inventory === null) {
            throw $this->createNotFoundException('Inventory item not found');
        }

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($inventory)));
    }

    #[Route('/inventories/{id}', name: 'inventory_show', methods: ['GET'])]
    #[OA\Get(
        operationId: 'getInventory',
        tags: ['Inventory'],
        summary: 'Get inventory by ID',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Inventory UUID')]
    public function showById(string $id): JsonResponse
    {
        $inventory = $this->cacheGet(
            sprintf('inventory_item_%s', $id),
            fn(): ?Inventory => $this->inventories->findActive($id),
        );

        if ($inventory === null) {
            throw $this->createNotFoundException('Inventory item not found');
        }

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($inventory)));
    }

    #[Route('/inventories/{id}/movements', name: 'inventory_movements_index', methods: ['GET'])]
    #[OA\Get(
        operationId: 'listMovements',
        tags: ['Movements'],
        summary: 'List movements for an inventory item',
    )]
    public function movements(Request $request, string $id): JsonResponse
    {
        // History is intentionally available for soft-deleted items too.
        $inventory = $this->findById($id)
            ?? throw $this->createNotFoundException('Inventory item not found');

        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        $items = $this->movements->findBy(
            ['inventory' => $inventory],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
            $limit,
            ($page - 1) * $limit,
        );

        $response = new JsonResponse(ApiResponse::success(array_map($this->mapper->toMovementResource(...), $items)));
        $response->headers->set('X-Total-Count', (string)$this->movements->count(['inventory' => $inventory]));

        return $response;
    }

    #[Route('/inventories/{id}/movements', name: 'inventory_movements_create', methods: ['POST'])]
    #[OA\Post(
        operationId: 'createMovement',
        tags: ['Movements'],
        summary: 'Create a stock movement',
    )]
    public function createMovement(
        #[MapEntity(expr: 'repository.findActive(id)')]
        Inventory $inventory,
        #[MapRequestPayload]
        CreateInventoryMovementRequest $input,
    ): JsonResponse {
        $result = $this->stock->apply(
            $inventory->getId()->toRfc4122(),
            $input->type,
            Decimal::format($input->quantity),
            $input->referenceType,
            $input->referenceId,
            $input->reason,
        );

        $status = $result->created ? Response::HTTP_CREATED : Response::HTTP_OK;

        $this->cache->invalidateTags([self::CACHE_TAG]);

        return new JsonResponse(ApiResponse::success($this->mapper->toMovementResource($result->movement)), $status);
    }

    #[Route('/inventories', name: 'inventory_create', methods: ['POST'])]
    #[OA\Post(
        operationId: 'createInventory',
        tags: ['Inventory'],
        summary: 'Create a new inventory item',
    )]
    public function create(#[MapRequestPayload] CreateInventoryRequest $input): JsonResponse
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

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($inventory), null), Response::HTTP_CREATED);
    }

    #[Route('/inventories/{id}', name: 'inventory_update', methods: ['PATCH'])]
    #[OA\Patch(
        operationId: 'updateInventory',
        tags: ['Inventory'],
        summary: 'Update inventory settings',
    )]
    public function update(string $id, #[MapRequestPayload] UpdateInventoryRequest $input): JsonResponse
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

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($inventory)));
    }

    #[Route('/inventories/{id}', name: 'inventory_delete', methods: ['DELETE'])]
    #[OA\Delete(
        operationId: 'deleteInventory',
        tags: ['Inventory'],
        summary: 'Soft delete an inventory item',
    )]
    public function delete(#[MapEntity(expr: 'repository.findActive(id)')] Inventory $inventory): Response
    {
        $this->stock->softDelete($inventory);
        $this->cache->invalidateTags([self::CACHE_TAG]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** History lookups include soft-deleted rows, so use the raw PK lookup. */
    private function findById(string $id): ?Inventory
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->inventories->find(Uuid::fromString($id));
    }

    /**
     * Read-through cache: tags the item so writes can invalidate it.
     *
     * @param callable(): mixed $callback
     */
    private function cacheGet(string $key, callable $callback): mixed
    {
        $cached = $this->cache->get($key, function (ItemInterface $item, bool &$save) use ($callback): mixed {
            $save = true;
            $item->tag([self::CACHE_TAG]);

            return $callback();
        });

        return $cached;
    }
}
