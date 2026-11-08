<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\CategoryDocument;
use App\Api\V1\Mapper\CategoryMapper;
use App\Api\V1\Request\SaveCategoryRequest;
use App\Entity\Category;
use App\Utils\ApiResponse;
use App\Utils\SlugGenerator;
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


#[Route('/categories', name: 'api_v1_category_')]
#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
        private readonly SlugGenerator $slugs,
        private readonly CategoryMapper $mapper,
    ) {}

    /** List all categories, ordered by position and then name. The response is cached for one hour. */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'All categories.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: CategoryDocument::class)),
                ),
            ],
            type: 'object',
        ),
    )]
    public function list(): JsonResponse
    {
        $data = $this->cache->get('categories.all', function (ItemInterface $item): array {
            $item->tag(['categories'])->expiresAfter(3600);

            return array_map(
                $this->mapper->toResource(...),
                $this->em->getRepository(Category::class)->findBy([], ['position' => 'ASC', 'name' => 'ASC']),
            );
        });

        return new JsonResponse(ApiResponse::success($data));
    }

    /** Create a category. The slug is generated from the name when it is omitted. */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Category created.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: CategoryDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 409, description: 'A category with this slug already exists.')]
    public function create(#[MapRequestPayload] SaveCategoryRequest $payload): JsonResponse
    {
        $category = new Category($payload->name, $payload->slug ?? $this->slugs->generate($payload->name, Category::class), $payload->position);
        $this->em->persist($category);
        $this->flush();

        $this->cache->invalidateTags(['categories']);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($category)), Response::HTTP_CREATED);
    }

    /** Rename a category, change its slug and/or move it to another position. */
    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Response(
        response: 200,
        description: 'Category updated.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: CategoryDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Category not found.')]
    #[OA\Response(response: 409, description: 'A category with this slug already exists.')]
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

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($category)));
    }

    /** Products of a deleted category keep existing, they just have no category any more. */
    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Category deleted. Products keep existing without a category.')]
    #[OA\Response(response: 404, description: 'Category not found.')]
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
}
