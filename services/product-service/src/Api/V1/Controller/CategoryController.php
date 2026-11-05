<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Request\SaveCategoryRequest;
use App\Entity\Category;
use App\Utils\ApiResponse;
use App\Utils\SlugGenerator;
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

#[Route('/categories', name: 'api_v1_category_')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
        private readonly SlugGenerator $slugs,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = $this->cache->get('categories.all', function (ItemInterface $item): array {
            $item->tag(['categories'])->expiresAfter(3600);

            return array_map(
                $this->toArray(...),
                $this->em->getRepository(Category::class)->findBy([], ['position' => 'ASC', 'name' => 'ASC']),
            );
        });

        return new JsonResponse(ApiResponse::success($data));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] SaveCategoryRequest $payload): JsonResponse
    {
        $category = new Category($payload->name, $payload->slug ?? $this->slugs->generate($payload->name, Category::class), $payload->position);
        $this->em->persist($category);
        $this->flush();

        $this->cache->invalidateTags(['categories']);

        return new JsonResponse(ApiResponse::success($this->toArray($category)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] SaveCategoryRequest $payload): JsonResponse
    {
        $category = $this->em->find(Category::class, $id) ?? throw $this->createNotFoundException('Category not found.');
        $slug = $payload->slug ?? ($payload->name === $category->getName()
            ? $category->getSlug()
            : $this->slugs->generate($payload->name, Category::class, $id));
        $category->rename($payload->name)->changeSlug($slug)->moveTo($payload->position);
        $this->flush();

        // the category name is embedded in cached product responses too
        $this->cache->invalidateTags(['categories', 'products']);

        return new JsonResponse(ApiResponse::success($this->toArray($category)));
    }

    /** Products of a deleted category keep existing, they just have no category any more. */
    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $category = $this->em->find(Category::class, $id) ?? throw $this->createNotFoundException('Category not found.');
        $this->em->remove($category);
        $this->em->flush();

        $this->cache->invalidateTags(['categories', 'products']);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function flush(): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('A category with this slug already exists.');
        }
    }

    private function toArray(Category $c): array
    {
        return ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug(), 'position' => $c->getPosition()];
    }
}
