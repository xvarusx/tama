<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Documentation\ReminderDocument;
use App\Api\V1\Mapper\ReminderMapper;
use App\Api\V1\Request\ListRemindersQuery;
use App\Enum\ReminderKind;
use App\Repository\ReminderRepository;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/reminders', name: 'api_v1_reminder_')]
#[OA\Tag(name: 'Reminders')]
final class RemindersController extends AbstractController
{
    public function __construct(
        private readonly ReminderRepository $reminders,
        private readonly ReminderMapper $mapper,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of reminders.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: ReminderDocument::class)),
                ),
                new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
            ],
            type: 'object',
        ),
    )]
    public function list(Request $request, #[MapQueryString] ListRemindersQuery $query): JsonResponse
    {
        $qb = $this->reminders->createQueryBuilder('r');

        if ($query->kind !== null) {
            $qb->andWhere('r.kind = :kind')
               ->setParameter('kind', $query->kind);
        }

        $total = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->orderBy('r.scheduledFor', 'ASC')
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
                'api_v1_reminder_list',
                [...$request->query->all(), 'page' => $page],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );

        return new JsonResponse(ApiResponse::success($data, $pagination));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The requested reminder.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: ReminderDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Reminder not found.')]
    public function show(string $id): JsonResponse
    {
        $reminder = $this->reminders->find($id)
            ?? throw $this->createNotFoundException('Reminder not found.');

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($reminder)));
    }
}