<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Mapper\ProductMapper;
use App\Api\V1\Request\CreateProductRequest;
use App\Api\V1\Request\ListProductsQuery;
use App\Api\V1\Request\UpdateProductRequest;
use App\Entity\Product;
use App\Hundler\Product\CreateProductHandler;
use App\Hundler\Product\UpdateProductHandler;
use App\Media\MediaStorage;
use App\Repository\ProductRepository;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/products', name: 'api_v1_product_')]
final class ProductsController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductMapper $mapper,
        private readonly CreateProductHandler $createProduct,
        private readonly UpdateProductHandler $updateProduct,
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
        private readonly MediaStorage $mediaStorage,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, #[MapQueryString] ListProductsQuery $query = new ListProductsQuery()): JsonResponse
    {
        [$items, $total] = $this->products->search(
            $query->page,
            $query->pageSize,
            $query->search,
            $query->categoryId,
            $query->brandId,
        );
        $data = array_map($this->mapper->toResource(...), $items);
        $pagination = Pagination::create(
            $query->page,
            $query->pageSize,
            $total,
            fn(int $page): string => $this->generateUrl(
                'api_v1_product_list',
                [...$request->query->all(), 'page' => $page],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );

        return new JsonResponse(ApiResponse::success($data, $pagination));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $product = $this->products->find($id) ?? throw $this->createNotFoundException('Product not found.');

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($product)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateProductRequest $payload): JsonResponse
    {
        $product = ($this->createProduct)($payload);

        $response = new JsonResponse(
            ApiResponse::success($this->mapper->toResource($product)),
            Response::HTTP_CREATED,
        );
        $this->cache->invalidateTags(['products']); // only after the commit succeeded

        $response->headers->set('Location', $this->generateUrl(
            'api_v1_product_show',
            ['id' => $product->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));

        return $response;
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] UpdateProductRequest $payload): JsonResponse
    {
        $product = $this->findOr404($id);

        $response = new JsonResponse(
            ApiResponse::success($this->mapper->toResource(($this->updateProduct)($id, $payload))),
            Response::HTTP_OK,
        );
        $this->cache->invalidateTags(['products']); // only after the commit succeeded

        // A Location header on a 200 response makes PHP change the status to 302
        // (the browser then follows the absolute URL and the SPA edit fails).
        // Content-Location keeps the same information without altering the status.
        $response->headers->set('Content-Location', $this->generateUrl(
            'api_v1_product_show',
            ['id' => $product->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));

        return $response;
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $product = $this->findOr404($id);
        $media = $product->getMedia()->toArray();

        $this->em->remove($product);
        $this->em->flush();

        // The DB rows are gone, so remove the files once the transaction committed.
        foreach ($media as $item) {
            $this->mediaStorage->delete($item);
        }

        $this->cache->invalidateTags(['products']);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function findOr404(int $id): Product
    {
        return $this->products->findActiveById($id) ?? throw $this->createNotFoundException('Product not found.');
    }
}
