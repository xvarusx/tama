<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\PaymentPlanDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\PaymentPlanMapper;
use App\Api\V1\Request\CreatePaymentPlanRequest;
use App\Api\V1\Request\ListPaymentPlansQuery;
use App\Handler\PaymentPlan\CancelPaymentPlanHandler;
use App\Handler\PaymentPlan\CreatePaymentPlanHandler;
use App\Repository\PaymentPlanRepository;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/payment-plans', name: 'api_v1_payment_plan_')]
#[OA\Tag(name: 'Payment Plans')]
final class PaymentPlansController extends AbstractController
{
    public function __construct(
        private readonly PaymentPlanRepository $plans,
        private readonly PaymentPlanMapper $mapper,
        private readonly CreatePaymentPlanHandler $createPaymentPlan,
        private readonly CancelPaymentPlanHandler $cancelPaymentPlan,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of payment plans.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PaymentPlanDocument::class)),
                ),
                new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
            ],
            type: 'object',
        ),
    )]
    public function list(Request $request, #[MapQueryString] ListPaymentPlansQuery $query): JsonResponse
    {
        $qb = $this->plans->createQueryBuilder('p');
        
        if ($query->invoiceId !== null) {
            $qb->andWhere('p.invoiceId = :invoiceId')
               ->setParameter('invoiceId', $query->invoiceId);
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($query->page - 1) * $query->pageSize)
            ->setMaxResults($query->pageSize)
            ->getQuery()
            ->getResult();

        $data = array_map($this->mapper->toResource(...), $items);
        
        $pagination = Pagination::create(
            $query->page,
            $query->pageSize,
            $total,
            fn(int $page): string => $this->generateUrl(
                'api_v1_payment_plan_list',
                [...$request->query->all(), 'page' => $page],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );

        return new JsonResponse(ApiResponse::success($data, $pagination));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The requested payment plan.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: PaymentPlanDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Payment plan not found.')]
    public function show(string $id): JsonResponse
    {
        $plan = $this->plans->find($id) 
            ?? throw $this->createNotFoundException('Payment plan not found.');

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($plan)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Payment plan created.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: PaymentPlanDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function create(#[MapRequestPayload] CreatePaymentPlanRequest $payload): JsonResponse
    {
        $plan = ($this->createPaymentPlan)($payload);

        $response = new JsonResponse(
            ApiResponse::success($this->mapper->toResource($plan)),
            Response::HTTP_CREATED,
        );

        $response->headers->set('Location', $this->generateUrl(
            'api_v1_payment_plan_show',
            ['id' => $plan->getId()->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));

        return $response;
    }

    #[Route('/{id}', name: 'cancel', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Payment plan canceled.')]
    public function cancel(string $id): JsonResponse
    {
        $plan = $this->plans->find($id) ?? throw $this->createNotFoundException('Payment plan not found.');

        $plan->cancel();
        
        $this->em->wrapInTransaction(function () use ($plan): void {
            $this->em->persist($plan);
            $this->em->flush();
        });

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($plan)));
    }
}