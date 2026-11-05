<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Request\PreviewSkuQuery;
use App\Api\V1\Request\PreviewSlugQuery;
use App\Entity\Brand;
use App\Entity\Category;
use App\Utils\ApiResponse;
use App\Utils\SkuGenerator;
use App\Utils\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "What would this slug / SKU be?" for the UI forms. Nothing is saved or reserved,
 * and nothing is cached: the answer depends on rows that change all the time.
 */
final class PreviewController extends AbstractController
{
    public function __construct(
        private readonly SlugGenerator $slugs,
        private readonly SkuGenerator $skus,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/slugs/preview', name: 'api_v1_preview_slug', methods: ['GET'])]
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

    #[Route('/skus/preview', name: 'api_v1_preview_sku', methods: ['GET'])]
    public function sku(#[MapQueryString] PreviewSkuQuery $query): JsonResponse
    {
        $brand = $this->em->find(Brand::class, $query->brandId)
            ?? throw new UnprocessableEntityHttpException('Brand does not exist.');

        return new JsonResponse(ApiResponse::success([
            'sku' => $this->skus->generate($brand->getName(), $query->name, $query->options),
        ]));
    }
}
