<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Request\SaveUnitRequest;
use App\Entity\Unit;
use App\Utils\ApiResponse;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/units', name: 'api_v1_unit_')]
final class UnitController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = $this->cache->get('units.all', function (ItemInterface $item): array {
            $item->tag(['units'])->expiresAfter(3600);

            return array_map($this->toArray(...), $this->em->getRepository(Unit::class)->findBy([], ['code' => 'ASC']));
        });

        return new JsonResponse(ApiResponse::success($data));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] SaveUnitRequest $payload): JsonResponse
    {
        $unit = new Unit($payload->code, $payload->allowDecimal);
        $this->em->persist($unit);
        $this->flush();

        $this->cache->invalidateTags(['units']);

        return new JsonResponse(ApiResponse::success($this->toArray($unit)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] SaveUnitRequest $payload): JsonResponse
    {
        $unit = $this->em->find(Unit::class, $id) ?? throw $this->createNotFoundException('Unit not found.');
        $unit->changeCode($payload->code)->setAllowDecimal($payload->allowDecimal);
        $this->flush();

        // the unit code is embedded in cached product responses too
        $this->cache->invalidateTags(['units', 'products']);

        return new JsonResponse(ApiResponse::success($this->toArray($unit)));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
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
