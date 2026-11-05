<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Media\MediaStorage;
use App\Repository\ProductMediaRepository;
use App\Repository\ProductRepository;
use App\Utils\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('', name: 'api_v1_media_')]
final class ProductMediaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MediaStorage $storage,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    #[Route('/products/{id}/media', name: 'upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function upload(
        int $id,
        Request $request,
        ProductRepository $products,
    ): JsonResponse {
        $product = $products->findActiveById($id)
            ?? throw $this->createNotFoundException('Product not found.');

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Send the image in the "file" field.');
        }

        $mime = $file->getMimeType();
        if (!isset(MediaStorage::ALLOWED_MIME_TYPES[(string)$mime])) {
            throw new UnsupportedMediaTypeHttpException('Only JPEG, PNG, WebP and SVG images are supported.');
        }

        if ((int)$file->getSize() > MediaStorage::MAX_BYTES) {
            throw new BadRequestHttpException('The image must be smaller than 5 MB.');
        }

        $position = $this->nextPosition($product);
        $media = $this->storage->store($file, $product, $position);
        $product->addMedia($media);

        $this->em->persist($media);
        $this->em->flush();

        $this->cache->invalidateTags(['products']);

        return new JsonResponse(ApiResponse::success($this->toArray($media)), Response::HTTP_CREATED);
    }

    #[Route('/media/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ProductMediaRepository $mediaRepository): BinaryFileResponse
    {
        $media = $mediaRepository->find($id)
            ?? throw $this->createNotFoundException('Media not found.');

        $path = $this->storage->absolutePath($media);
        if (!is_file($path)) {
            throw $this->createNotFoundException('Media file not found.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', (string)$media->getKind());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        if ($media->getKind() === 'image/svg+xml') {
            // An SVG is an HTML document: never let it run scripts on our origin.
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            );
        }

        return $response;
    }

    #[Route('/media/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, ProductMediaRepository $mediaRepository): Response
    {
        $media = $mediaRepository->find($id)
            ?? throw $this->createNotFoundException('Media not found.');

        $product = $media->getProduct();
        $product?->removeMedia($media);

        $this->em->remove($media);
        $this->em->flush();

        $this->storage->delete($media);
        $this->cache->invalidateTags(['products']);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function nextPosition(Product $product): int
    {
        $max = $this->em
            ->createQuery('SELECT MAX(m.position) FROM ' . ProductMedia::class . ' m WHERE m.product = :product')
            ->setParameter('product', $product)
            ->getSingleScalarResult();

        return $max === null ? 0 : ((int)$max) + 1;
    }

    /** @return array{id: int|null, url: string, kind: string|null, position: int|null} */
    private function toArray(ProductMedia $media): array
    {
        return [
            'id' => $media->getId(),
            'url' => '/media/' . $media->getId(),
            'kind' => $media->getKind(),
            'position' => $media->getPosition(),
        ];
    }
}
