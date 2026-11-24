<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\InstallmentDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\InstallmentMapper;
use App\Api\V1\Request\ListInstallmentsQuery;
use App\Repository\InstallmentRepository;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/installments', name: 'api_v1_installment_')]
#[OA\Tag(name: 'Installments')]
final class InstallmentsController extends AbstractController
{
    public function __construct(
        private readonly InstallmentRepository $installments,
        private readonly InstallmentMapper $mapper,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of installments.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: InstallmentDocument::class)),
                ),
                new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
            ],
            type: 'object',
        ),
    )]
    public function list(Request $request, #[MapQueryString] ListInstallmentsQuery $query): JsonResponse
    {
        $qb = $this->installments->createQueryBuilder('i');
        
        if ($query->status !== null) {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $query->status);
        }
        
        if ($query->paymentPlanId !== null) {
            $qb->andWhere('i.paymentPlan = :paymentPlanId')
               ->setParameter('paymentPlanId', $query->paymentPlanId);
        }
        
        if ($query->overdueOnly) {
            $today = new \DateTimeImmutable();
            $qb->andWhere('i.dueDate < :today')
               ->andWhere('i.status IN (:statuses)')
               ->setParameter('today', $today)
               ->setParameter('statuses', [\App\Enum\InstallmentStatus::UNPAID, \App\Enum\InstallmentStatus::PARTIALLY_PAID]);
        }

        $total = (int) (clone $qb)->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->orderBy('i.dueDate', 'ASC')
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
                'api_v1_installment_list',
                [...$request->query->all(), 'page' => $page],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );

        return new JsonResponse(ApiResponse::success($data, $pagination));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The requested installment.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: InstallmentDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Installment not found.')]
    public function show(string $id): JsonResponse
    {
        $installment = $this->installments->find($id) 
            ?? throw $this->createNotFoundException('Installment not found.');

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($installment)));
    }
}