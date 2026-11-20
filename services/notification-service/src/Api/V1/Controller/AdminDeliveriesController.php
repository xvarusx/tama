<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\DeliveryDocument;
use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\DeliveryMapper;
use App\Api\V1\Request\ListDeliveriesQuery;
use App\Dto\DeliveryFilter;
use App\Exceptions\DeliveryNotFound;
use App\Handler\Delivery\RequeueDeliveryHandler;
use App\Repository\DeliveryRepository;
use App\Security\CallerResolver;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/deliveries', name: 'api_v1_admin_delivery_')]
#[OA\Tag(name: 'Admin Deliveries')]
final class AdminDeliveriesController extends AbstractController
{
    public function __construct(
        private readonly DeliveryRepository $deliveries,
        private readonly DeliveryMapper $mapper,
        private readonly CallerResolver $callers,
        private readonly RequeueDeliveryHandler $requeue,
    ) {}

    /** Paginated deliveries with status/channel/type/search filters. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated deliveries.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: DeliveryDocument::class))),
                new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 403, description: 'Administrator role required.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function list(
        Request $request,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ListDeliveriesQuery $query = new ListDeliveriesQuery(),
    ): JsonResponse {
        $this->callers->requireAdmin($this->callers->resolve($request));
        $filter = new DeliveryFilter(
            page: $query->page,
            pageSize: $query->pageSize,
            status: $query->status,
            channel: $query->channel,
            type: $query->type,
            search: $query->search,
        );

        [$items, $total] = $this->deliveries->search($filter);
        $pagination = Pagination::create(
            $query->page,
            $query->pageSize,
            $total,
            fn(int $page): string => $this->generateUrl(
                'api_v1_admin_delivery_list',
                [...$request->query->all(), 'page' => $page],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );

        return new JsonResponse(ApiResponse::success(
            array_map($this->mapper->toResource(...), $items),
            $pagination,
        ));
    }

    /** One delivery with its attempt history. */
    #[Route('/{id}', name: 'show', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The delivery and its attempts.', content: new OA\JsonContent(ref: new Model(type: DeliveryDocument::class)))]
    #[OA\Response(response: 404, description: 'Delivery not found.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function show(string $id, Request $request): JsonResponse
    {
        $this->callers->requireAdmin($this->callers->resolve($request));
        $delivery = $this->deliveries->findWithAttempts(Uuid::fromString($id)) ?? throw new DeliveryNotFound(Uuid::fromString($id));

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($delivery, withBody: true)));
    }

    /** Requeue a failed delivery. Any other status conflicts. */
    #[Route('/{id}/requeue', name: 'requeue', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The requeued delivery.', content: new OA\JsonContent(ref: new Model(type: DeliveryDocument::class)))]
    #[OA\Response(response: 409, description: 'The delivery is not failed.', content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)))]
    public function requeue(string $id, Request $request): JsonResponse
    {
        $this->callers->requireAdmin($this->callers->resolve($request));
        $delivery = ($this->requeue)(Uuid::fromString($id));

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($delivery)));
    }
}
