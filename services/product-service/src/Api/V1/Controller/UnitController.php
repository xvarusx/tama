<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\UnitDocument;
use App\Api\V1\Request\SaveUnitRequest;
use App\Entity\Unit;
use App\Utils\ApiResponse;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/units', name: 'api_v1_unit_')]
#[OA\Tag(name: 'Units')]
final class UnitController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    /** List all units, sorted by code. The response is cached for one hour. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'All units.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: UnitDocument::class)),
                ),
            ],
            type: 'object',
        ),
    )]
    public function list(): JsonResponse
    {
        $data = $this->cache->get('units.all', function (ItemInterface $item): array {
            $item->tag(['units'])->expiresAfter(3600);

            return array_map($this->toArray(...), $this->em->getRepository(Unit::class)->findBy([], ['code' => 'ASC']));
        });

        return new JsonResponse(ApiResponse::success($data));
    }

    /** Create a unit. `allowDecimal` defaults to false. */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Unit created.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: UnitDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 409, description: 'A unit with this code already exists.')]
    public function create(#[MapRequestPayload] SaveUnitRequest $payload): JsonResponse
    {
        $unit = new Unit($payload->code, $payload->allowDecimal);
        $this->em->persist($unit);
        $this->flush();

        $this->cache->invalidateTags(['units']);

        return new JsonResponse(ApiResponse::success($this->toArray($unit)), Response::HTTP_CREATED);
    }

    /** Change a unit's code and whether it allows decimal quantities. */
    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Response(
        response: 200,
        description: 'Unit updated.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: UnitDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Unit not found.')]
    #[OA\Response(response: 409, description: 'A unit with this code already exists.')]
    public function update(int $id, #[MapRequestPayload] SaveUnitRequest $payload): JsonResponse
    {
        $unit = $this->em->find(Unit::class, $id) ?? throw $this->createNotFoundException('Unit not found.');
        $unit->changeCode($payload->code)->setAllowDecimal($payload->allowDecimal);
        $this->flush();

        // the unit code is embedded in cached product responses too
        $this->cache->invalidateTags(['units', 'products']);

        return new JsonResponse(ApiResponse::success($this->toArray($unit)));
    }

    /** Delete a unit. Units still referenced by products cannot be deleted. */
    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Unit deleted.')]
    #[OA\Response(response: 404, description: 'Unit not found.')]
    #[OA\Response(response: 409, description: 'The unit is used by products and cannot be deleted.')]
    public function delete(int $id): Response
    {
        $unit = $this->em->find(Unit::class, $id) ?? throw $this->createNotFoundException('Unit not found.');

        try {
            $this->em->remove($unit);
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new ConflictHttpException('This unit is used by products and cannot be deleted.');
        }

        $this->cache->invalidateTags(['units']);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function flush(): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('A unit with this code already exists.');
        }
    }

    private function toArray(Unit $u): array
    {
        return ['id' => $u->getId(), 'code' => $u->getCode(), 'allowDecimal' => $u->isAllowDecimal()];
    }
}
