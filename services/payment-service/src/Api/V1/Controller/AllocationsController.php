<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\PaymentAllocationDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\PaymentAllocationMapper;
use App\Handler\Installment\AllocatePaymentHandler;
use App\Repository\PaymentAllocationRepository;
use App\Utils\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/allocations', name: 'api_v1_allocation_')]
#[OA\Tag(name: 'Allocations')]
final class AllocationsController extends AbstractController
{
    public function __construct(
        private readonly PaymentAllocationRepository $allocations,
        private readonly PaymentAllocationMapper $mapper,
        private readonly AllocatePaymentHandler $allocatePayment,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of allocations.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PaymentAllocationDocument::class)),
                ),
            ],
            type: 'object',
        ),
    )]
    public function list(): JsonResponse
    {
        $items = $this->allocations->findAll();
        $data = array_map($this->mapper->toResource(...), $items);

        return new JsonResponse(ApiResponse::success($data));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The requested allocation.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: PaymentAllocationDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Allocation not found.')]
    public function show(string $id): JsonResponse
    {
        $allocation = $this->allocations->find($id) 
            ?? throw $this->createNotFoundException('Allocation not found.');

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($allocation)));
    }

    #[Route('', name: 'allocate', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Payment allocated to an installment.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: PaymentAllocationDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 400, description: 'Invalid request data.')]
    public function allocate(#[MapRequestPayload] AllocatePaymentRequest $payload): JsonResponse
    {
        $allocation = ($this->allocatePayment)($payload->paymentId, $payload->amount);

        return new JsonResponse(
            ApiResponse::success($this->mapper->toResource($allocation)),
            Response::HTTP_CREATED,
        );
    }
}