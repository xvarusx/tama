<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\SkuPreviewDocument;
use App\Api\V1\Documentation\SlugPreviewDocument;
use App\Api\V1\Request\PreviewSkuQuery;
use App\Api\V1\Request\PreviewSlugQuery;
use App\Entity\Brand;
use App\Entity\Category;
use App\Utils\ApiResponse;
use App\Utils\SkuGenerator;
use App\Utils\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "What would this slug / SKU be?" for the UI forms. Nothing is saved or reserved,
 * and nothing is cached: the answer depends on rows that change all the time.
 */
#[OA\Tag(name: 'Preview')]
final class PreviewController extends AbstractController
{
    public function __construct(
        private readonly SlugGenerator $slugs,
        private readonly SkuGenerator $skus,
        private readonly EntityManagerInterface $em,
    ) {}

    /** Preview the slug that a brand or category name would produce. */
    #[Route('/slugs/preview', name: 'api_v1_preview_slug', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The generated slug.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: SlugPreviewDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 422, description: 'Unknown entity type.')]
    public function slug(#[MapQueryString] PreviewSlugQuery $query): JsonResponse
    {
        $class = match ($query->entity) {
            'brand' => Brand::class,
            'category' => Category::class,
            default => throw new UnprocessableEntityHttpException('Unknown entity type.'),
        };

        return new JsonResponse(ApiResponse::success([
            'slug' => $this->slugs->generate($query->name, $class, $query->ignoreId),
        ]));
    }

    /** Preview the SKU that a brand, product name and options would produce. */
    #[Route('/skus/preview', name: 'api_v1_preview_sku', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The generated SKU.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: SkuPreviewDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 422, description: 'The brand does not exist.')]
    public function sku(#[MapQueryString] PreviewSkuQuery $query): JsonResponse
    {
        $brand = $this->em->find(Brand::class, $query->brandId)
            ?? throw new UnprocessableEntityHttpException('Brand does not exist.');

        return new JsonResponse(ApiResponse::success([
            'sku' => $this->skus->generate($brand->getName(), $query->name, $query->options),
        ]));
    }
}
