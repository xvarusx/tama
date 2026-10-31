<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Message\ProductUpdated;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class ProductsController extends AbstractController
{
    private const READ_CONTEXT = ['groups' => ['product:read']];

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
    ) {}

    #[Route('/api/product', name: 'app_api_product', methods: ['GET'])]
    public function index(EntityManagerInterface $em, Request $request, SerializerInterface $serializer): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 10)));
        $key = sprintf('products_page_%d_limit_%d', $page, $limit);

        $json = $this->cache->get(
            $key,
            function (ItemInterface $item) use ($page, $limit, $em, $serializer): string {
                $item->expiresAfter(300);
                $item->tag(['products']);

                return $serializer->serialize(
                    $this->findWithPagination($em, $page, $limit),
                    'json',
                    self::READ_CONTEXT
                );
            }
        );

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/api/product/{id<\d+>}', name: 'app_api_product_showbyid', methods: ['GET'])]
    public function showById(EntityManagerInterface $em, SerializerInterface $serializer, int $id): JsonResponse
    {
        $json = $this->cache->get(
            sprintf('product_id_%d', $id),
            function (ItemInterface $item) use ($em, $serializer, $id): string {
                $item->expiresAfter(300);
                $item->tag(['products']);

                $product = $em->getRepository(Product::class)->find($id);

                if (null === $product) {
                    throw $this->createNotFoundException('Product not found');
                }

                return $serializer->serialize($product, 'json', self::READ_CONTEXT);
            }
        );

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/api/product/', name: 'app_api_product_create', methods: ['POST'])]
    public function create(EntityManagerInterface $em, Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse
    {
        try {
            $product = $serializer->deserialize($request->getContent(), Product::class, 'json');
        } catch (SerializerExceptionInterface $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $now = new DateTimeImmutable();
        $product->setCreatedAt($now);
        $product->setUpdatedAt($now);

        $em->persist($product);
        $em->flush();

        $this->cache->invalidateTags(['products']);

        return $this->json($product, Response::HTTP_CREATED, [], self::READ_CONTEXT);
    }

    #[Route('/api/product/{id<\d+>}', name: 'app_api_product_update', methods: ['PUT', 'PATCH'])]
    public function update(EntityManagerInterface $em, Request $request, SerializerInterface $serializer, MessageBusInterface $bus, int $id, ValidatorInterface $validator): JsonResponse
    {
        $product = $em->getRepository(Product::class)->find($id);

        if (null === $product) {
            throw $this->createNotFoundException('Product not found');
        }

        try {
            $updatedProduct = $serializer->deserialize(
                $request->getContent(),
                Product::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $product]
            );
        } catch (SerializerExceptionInterface $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $errors = $validator->validate($updatedProduct);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $product->setUpdatedAt(new DateTimeImmutable());
        $em->flush();

        $bus->dispatch(new ProductUpdated(
            productId: $product->getId(),
            sku: $product->getSku(),
            name: $product->getName(),
            sellingPrice: $product->getSellingPrice(),
            unit: $product->getUnit(),
            status: $product->getStatus()
        ));

        $this->cache->invalidateTags(['products']);

        return $this->json($product, Response::HTTP_OK, [], self::READ_CONTEXT);
    }


    private function findWithPagination(EntityManagerInterface $em, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        return $em->getRepository(Product::class)->findBy([], ['name' => 'ASC'], $limit, $offset);
    }
}
