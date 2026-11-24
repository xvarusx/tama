<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\OrderDocument;
use App\Api\V1\Mapper\OrderMapper;
use App\Api\V1\Request\CreateOrderRequest;
use App\Api\V1\Request\ReceiveGoodsRequest;
use App\Api\V1\Request\ShipOrderRequest;
use App\Api\V1\Request\UpdateOrderRequest;
use App\Entity\Order;
use App\Hundler\Order\CancelOrderHandler;
use App\Hundler\Order\CreateOrderHandler;
use App\Hundler\Order\MarkDeliveredHandler;
use App\Hundler\Order\ReceiveGoodsHandler;
use App\Hundler\Order\ShipOrderHandler;
use App\Hundler\Order\SubmitOrderHandler;
use App\Hundler\Order\UpdateOrderHandler;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Orders', description: 'Order management API')]
#[Route('/orders', name: 'api_orders_')]
class OrdersController extends AbstractController
{
    private const UUID_REQUIREMENT = Requirement::UID_RFC4122;

    public function __construct(
        private OrderRepository $repository,
        private OrderMapper $mapper,
        private EntityManagerInterface $em,
        private CreateOrderHandler $createOrderHandler,
        private SubmitOrderHandler $submitOrderHandler,
        private UpdateOrderHandler $updateOrderHandler,
        private ReceiveGoodsHandler $receiveGoodsHandler,
        private ShipOrderHandler $shipOrderHandler,
        private CancelOrderHandler $cancelOrderHandler,
        private MarkDeliveredHandler $markDeliveredHandler,
    ) {}

    #[OA\Response(
        response: 200,
        description: 'List of orders',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: OrderDocument::class)))
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $orders = $this->repository->findBy([], ['id' => 'DESC']);
        $data = array_map(fn(Order $order) => $this->mapper->toResource($order), $orders);

        return $this->json($data);
    }

    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/CreateOrderRequest')
    )]
    #[OA\Response(
        response: 201,
        description: 'Order created successfully',
        content: new OA\JsonContent(ref: new Model(type: OrderDocument::class))
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateOrderRequest $request,
    ): JsonResponse {
        $order = ($this->createOrderHandler)($request);

        return $this->json($this->mapper->toResource($order), 201);
    }

    #[OA\Response(
        response: 200,
        description: 'Order details',
        content: new OA\JsonContent(ref: new Model(type: OrderDocument::class))
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => self::UUID_REQUIREMENT])]
    public function show(string $id): JsonResponse
    {
        $order = $this->repository->find(Uuid::fromString($id));
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        return $this->json($this->mapper->toResource($order));
    }

    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/UpdateOrderRequest')
    )]
    #[OA\Response(
        response: 200,
        description: 'Order updated',
        content: new OA\JsonContent(ref: new Model(type: OrderDocument::class))
    )]
    #[Route('/{id}', name: 'update', methods: ['PATCH'], requirements: ['id' => self::UUID_REQUIREMENT])]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateOrderRequest $request,
    ): JsonResponse {
        $order = $this->repository->find(Uuid::fromString($id));
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        $order = ($this->updateOrderHandler)($order, $request);

        return $this->json($this->mapper->toResource($order));
    }

    #[OA\Response(response: 204, description: 'Order deleted')]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => self::UUID_REQUIREMENT])]
    public function delete(string $id): JsonResponse
    {
        $order = $this->repository->find(Uuid::fromString($id));
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        $this->em->remove($order);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[OA\Response(
        response: 200,
        description: 'Order submitted',
        content: new OA\JsonContent(ref: new Model(type: OrderDocument::class))
    )]
    #[Route('/{id}/submit', name: 'submit', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    public function submit(string $id): JsonResponse
    {
        $order = $this->repository->find(Uuid::fromString($id));
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        try {
            $order = ($this->submitOrderHandler)($order);
        } catch (\App\Exceptions\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->mapper->toResource($order));
    }

    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/ReceiveGoodsRequest')
    )]
    #[OA\Response(
        response: 200,
        description: 'Goods received',
        content: new OA\JsonContent(ref: new Model(type: OrderDocument::class))
    )]
    #[Route('/{id}/receive', name: 'receive', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    public function receive(
        string $id,
        #[MapRequestPayload] ReceiveGoodsRequest $request,
    ): JsonResponse {
        $order = $this->repository->find(Uuid::fromString($id));
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        try {
            $order = ($this->receiveGoodsHandler)($order, $request);
        } catch (\App\Exceptions\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->mapper->toResource($order));
    }

    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/ShipOrderRequest')
    )]
    #[OA\Response(
        response: 200,
        description: 'Order shipped',
        content: new OA\JsonContent(ref: new Model(type: OrderDocument::class))
    )]
    #[Route('/{id}/ship', name: 'ship', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    public function ship(
        string $id,
        #[MapRequestPayload] ShipOrderRequest $request,
    ): JsonResponse {
        $order = $this->repository->find(Uuid::fromString($id));
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        try {
            $order = ($this->shipOrderHandler)($order, $request);
        } catch (\App\Exceptions\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->mapper->toResource($order));
    }

    #[OA\Response(
        response: 200,
        description: 'Order marked delivered',
        content: new OA\JsonContent(ref: new Model(type: OrderDocument::class))
    )]
    #[Route('/{id}/deliver', name: 'deliver', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    public function deliver(string $id): JsonResponse
    {
        $order = $this->repository->find(Uuid::fromString($id));
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        try {
            $order = ($this->markDeliveredHandler)($order);
        } catch (\App\Exceptions\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->mapper->toResource($order));
    }

    #[OA\Response(
        response: 200,
        description: 'Order canceled',
        content: new OA\JsonContent(ref: new Model(type: OrderDocument::class))
    )]
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => self::UUID_REQUIREMENT])]
    public function cancel(string $id): JsonResponse
    {
        $order = $this->repository->find(Uuid::fromString($id));
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        try {
            $order = ($this->cancelOrderHandler)($order);
        } catch (\App\Exceptions\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json($this->mapper->toResource($order));
    }
}