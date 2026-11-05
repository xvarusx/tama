<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Request\SaveBrandRequest;
use App\Entity\Brand;
use App\Utils\ApiResponse;
use App\Utils\SlugGenerator;
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

#[Route('/brands', name: 'api_v1_brand_')]
final class BrandController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
        private readonly SlugGenerator $slugs,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = $this->cache->get('brands.all', function (ItemInterface $item): array {
            $item->tag(['brands'])->expiresAfter(3600);

            return array_map($this->toArray(...), $this->em->getRepository(Brand::class)->findBy([], ['name' => 'ASC']));
        });

        return new JsonResponse(ApiResponse::success($data));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] SaveBrandRequest $payload): JsonResponse
    {
        $brand = new Brand($payload->name, $payload->slug ?? $this->slugs->generate($payload->name, Brand::class));
        $this->em->persist($brand);
        $this->flush();

        $this->cache->invalidateTags(['brands']);

        return new JsonResponse(ApiResponse::success($this->toArray($brand)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] SaveBrandRequest $payload): JsonResponse
    {
        $brand = $this->em->find(Brand::class, $id) ?? throw $this->createNotFoundException('Brand not found.');
        // the slug only changes when the name changes (or when the client sends one)
        $slug = $payload->slug ?? ($payload->name === $brand->getName()
            ? $brand->getSlug()
            : $this->slugs->generate($payload->name, Brand::class, $id));
        $brand->rename($payload->name)->changeSlug($slug);
        $this->flush();

        // the brand name is embedded in cached product responses too
        $this->cache->invalidateTags(['brands', 'products']);

        return new JsonResponse(ApiResponse::success($this->toArray($brand)));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $brand = $this->em->find(Brand::class, $id) ?? throw $this->createNotFoundException('Brand not found.');

        try {
            $this->em->remove($brand);
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new ConflictHttpException('This brand is used by products and cannot be deleted.');
        }

        $this->cache->invalidateTags(['brands']);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function flush(): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('A brand with this name or slug already exists.');
        }
    }

    private function toArray(Brand $b): array
    {
        return ['id' => $b->getId(), 'name' => $b->getName(), 'slug' => $b->getSlug()];
    }
}
