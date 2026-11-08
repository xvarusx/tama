<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Entity\Brand;
use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductVariant;
use App\Entity\Unit;
use App\Media\MediaStorage;
use App\Repository\ProductMediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class ProductMediaControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $entityManager = $this->em();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get('doctrine')->getManager();
    }

    private function seedProduct(): Product
    {
        $em = $this->em();
        $brand = new Brand('Media Brand', 'media-brand');
        $unit = new Unit('pcs', false);
        $em->persist($brand);
        $em->persist($unit);

        $product = new Product('Media Product', $brand, $unit);
        $product->addProductVariant(new ProductVariant('MEDIA-SKU-1', '1.00', '2.00'));
        $em->persist($product);
        $em->flush();

        return $product;
    }

    private function uploadedFile(string $contents, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'product-media-');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    private function png(): UploadedFile
    {
        return $this->uploadedFile(
            (string)base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='),
            'pixel.png',
            'image/png',
        );
    }

    public function testUploadShowAndDeleteMedia(): void
    {
        $product = $this->seedProduct();

        $this->client->request('POST', '/api/v1/products/' . $product->getId() . '/media', [], [
            'file' => $this->png(),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        $mediaId = $body['data']['id'];

        self::assertSame('/media/' . $mediaId, $body['data']['url']);
        self::assertSame('image/png', $body['data']['kind']);

        // The product contract exposes the uploaded image.
        $this->client->request('GET', '/api/v1/products/' . $product->getId());
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $body['data']['attributes']['media']);
        self::assertSame($mediaId, $body['data']['attributes']['media'][0]['id']);

        $this->client->request('GET', '/api/v1/media/' . $mediaId);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertStringStartsWith('image/png', (string)$this->client->getResponse()->headers->get('Content-Type'));

        $media = static::getContainer()->get(ProductMediaRepository::class)->find($mediaId);
        self::assertInstanceOf(ProductMedia::class, $media);
        $path = static::getContainer()->get(MediaStorage::class)->absolutePath($media);
        self::assertFileExists($path);

        $this->client->request('DELETE', '/api/v1/media/' . $mediaId);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertFileDoesNotExist($path);

        $this->client->request('GET', '/api/v1/media/' . $mediaId);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUploadRejectsUnsupportedMime(): void
    {
        $product = $this->seedProduct();

        $this->client->request('POST', '/api/v1/products/' . $product->getId() . '/media', [], [
            'file' => $this->uploadedFile('not an image', 'notes.txt', 'text/plain'),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }

    public function testSvgIsServedWithARestrictivePolicy(): void
    {
        $product = $this->seedProduct();

        $this->client->request('POST', '/api/v1/products/' . $product->getId() . '/media', [], [
            'file' => $this->uploadedFile(
                '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1"/></svg>',
                'pixel.svg',
                'image/svg+xml',
            ),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('image/svg+xml', $body['data']['kind']);

        $this->client->request('GET', '/api/v1/media/' . $body['data']['id']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertStringStartsWith(
            'image/svg+xml',
            (string)$this->client->getResponse()->headers->get('Content-Type'),
        );
        self::assertStringContainsString(
            'sandbox',
            (string)$this->client->getResponse()->headers->get('Content-Security-Policy'),
        );
    }

    public function testDeleteRejectsStoredPathsOutsideGeneratedFormat(): void
    {
        $storage = static::getContainer()->get(MediaStorage::class);

        $this->expectException(InvalidArgumentException::class);
        $storage->delete(new ProductMedia('../outside.txt', 'image/png'));
    }
}
