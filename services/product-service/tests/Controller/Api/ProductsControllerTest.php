<?php

namespace App\Tests\Controller\Api;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProductsControllerTest extends WebTestCase
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
        $this->client->getContainer()->get('cache.products')->clear();
    }

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get('doctrine')->getManager();
    }

    private function seedCategory(string $name): Category
    {
        $em = $this->em();
        $now = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');

        $category = (new Category())
            ->setName($name)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $em->persist($category);
        $em->flush();

        return $category;
    }

    private function seedProduct(string $name = 'Test Product', ?string $categoryName = 'Test Category'): Product
    {
        $em = $this->em();
        $now = new \DateTimeImmutable('2026-09-20T12:00:00+00:00');

        $product = (new Product())
            ->setName($name)
            ->setSku('TEST-'.bin2hex(random_bytes(4)))
            ->setDescription('Test description')
            ->setBrand('Test brand')
            ->setUnit('kg')
            ->setSellingPrice('12')
            ->setCostPrice('10')
            ->setImageUrl('https://example.com/image.png')
            ->setStatus('active')
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        if (null !== $categoryName) {
            $category = (new Category())
                ->setName($categoryName)
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $em->persist($category);
            $product->setCategory($category);
        }

        $em->persist($product);
        $em->flush();
        $em->clear();

        return $product;
    }

    private function removeProducts(): void
    {
        $em = $this->em();

        foreach ($em->getRepository(Product::class)->findAll() as $product) {
            $em->remove($product);
        }
        $em->flush();

        foreach ($em->getRepository(Category::class)->findAll() as $category) {
            $em->remove($category);
        }
        $em->flush();
        $em->clear();
    }

    public function testIndexSerializesProductsWithTheirCategory(): void
    {
        $this->removeProducts();
        $product = $this->seedProduct('Alpha Product', 'Screens');

        $this->client->request('GET', '/api/product');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertCount(1, $body);
        self::assertSame($product->getId(), $body[0]['id']);
        self::assertSame('Alpha Product', $body[0]['name']);
        self::assertSame('Screens', $body[0]['category']['name']);
        self::assertArrayNotHasKey('products', $body[0]['category']);

        $this->removeProducts();
    }

    public function testShowByIdSerializesProductWithItsCategory(): void
    {
        $this->removeProducts();
        $product = $this->seedProduct('Gamma Product', 'Keyboards');

        $this->client->request('GET', '/api/product/'.$product->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame($product->getId(), $body['id']);
        self::assertSame('Gamma Product', $body['name']);
        self::assertSame('Keyboards', $body['category']['name']);
        self::assertArrayNotHasKey('products', $body['category']);

        $this->removeProducts();
    }

    public function testShowMissingProductReturns404(): void
    {
        $this->removeProducts();

        $this->client->request('GET', '/api/product/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateProductReturnsTheSerializedProduct(): void
    {
        $this->removeProducts();

        $this->client->request('POST', '/api/product/', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'New Product',
            'sku' => 'NEW-001',
            'description' => 'New description',
            'brand' => 'New brand',
            'unit' => 'kg',
            'sellingPrice' => '20',
            'costPrice' => '15',
            'status' => 'active',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('New Product', $body['name']);
        self::assertSame('NEW-001', $body['sku']);
        self::assertNotNull($body['id']);

        $em = $this->em();
        $em->clear();
        self::assertNotNull($em->getRepository(Product::class)->find($body['id']));

        $this->removeProducts();
    }

    public function testCreateLinksACategoryPassedAsAnId(): void
    {
        $this->removeProducts();
        $category = $this->seedCategory('Screens');

        $this->client->request('POST', '/api/product/', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Categorised Product',
            'sku' => 'CAT-001',
            'unit' => 'kg',
            'sellingPrice' => '20',
            'costPrice' => '15',
            'status' => 'active',
            'category' => (string) $category->getId(),
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame($category->getId(), $body['category']['id']);
        self::assertSame('Screens', $body['category']['name']);

        $em = $this->em();
        $em->clear();
        $created = $em->getRepository(Product::class)->find($body['id']);

        self::assertNotNull($created);
        self::assertSame($category->getId(), $created->getCategory()->getId());

        $this->removeProducts();
    }

    public function testCreateWithAnUnknownCategoryReturns422(): void
    {
        $this->removeProducts();

        $this->client->request('POST', '/api/product/', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Orphan Product',
            'sku' => 'ORPHAN-001',
            'unit' => 'kg',
            'sellingPrice' => '20',
            'costPrice' => '15',
            'status' => 'active',
            'category' => '999999',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('Category with id "999999" was not found.', $body['error']);

        $em = $this->em();
        $em->clear();
        self::assertNull($em->getRepository(Product::class)->findOneBy(['sku' => 'ORPHAN-001']));
    }

    public function testUpdateLinksACategoryPassedAsAnId(): void
    {
        $this->removeProducts();
        $this->seedCategory('Screens');
        $keyboards = $this->seedCategory('Keyboards');
        $product = $this->seedProduct('Uncategorised Product', null);
        $id = $product->getId();

        $this->client->request('PUT', '/api/product/'.$id, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'unit' => 'kg',
            'sellingPrice' => '20',
            'costPrice' => '15',
            'status' => 'active',
            'category' => (string) $keyboards->getId(),
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame($keyboards->getId(), $body['category']['id']);
        self::assertSame('Keyboards', $body['category']['name']);

        $em = $this->em();
        $em->clear();
        $updated = $em->getRepository(Product::class)->find($id);

        self::assertNotNull($updated);
        self::assertSame($keyboards->getId(), $updated->getCategory()->getId());

        $this->removeProducts();
    }

    public function testUpdateUsesTheRouteIdInsteadOfCreatingANewProduct(): void
    {
        $this->removeProducts();
        $product = $this->seedProduct('Old Name', 'Screens');
        $id = $product->getId();

        $this->client->request('PUT', '/api/product/'.$id, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Renamed Product',
            'sku' => $product->getSku(),
            'unit' => 'kg',
            'sellingPrice' => '30',
            'costPrice' => '20',
            'status' => 'active',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame($id, $body['id']);
        self::assertSame('Renamed Product', $body['name']);

        $em = $this->em();
        $em->clear();
        $updated = $em->getRepository(Product::class)->find($id);

        self::assertNotNull($updated);
        self::assertSame('Renamed Product', $updated->getName());
        self::assertSame('30', $updated->getSellingPrice());
        self::assertCount(1, $em->getRepository(Product::class)->findAll());

        $this->removeProducts();
    }

    public function testUpdateMissingProductReturns404(): void
    {
        $this->removeProducts();

        $this->client->request('PUT', '/api/product/999999', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Ghost']));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdatePersistsChangedFieldsAndStatus(): void
    {
        $this->removeProducts();
        $product = $this->seedProduct('Old Name', 'Screens');
        $id = $product->getId();

        $this->client->request('PUT', '/api/product/'.$id, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Renamed Product',
            'sku' => $product->getSku(),
            'unit' => 'kg',
            'sellingPrice' => '30',
            'costPrice' => '20',
            'status' => 'archive',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($id, $body['id']);
        self::assertSame('Renamed Product', $body['name']);
        self::assertSame('archive', $body['status']);

        $em = $this->em();
        $em->clear();
        $updated = $em->getRepository(Product::class)->find($id);
        self::assertSame('Renamed Product', $updated->getName());
        self::assertSame('30', $updated->getSellingPrice());
        self::assertSame('archive', $updated->getStatus());
        self::assertCount(1, $em->getRepository(Product::class)->findAll());
    }

    public function testPatchCanChangeOnlyStatus(): void
    {
        $this->removeProducts();
        $product = $this->seedProduct('Only Status', 'Screens');
        $id = $product->getId();

        $this->client->request('PATCH', '/api/product/'.$id, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 'archive']));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $em = $this->em();
        $em->clear();
        $updated = $em->getRepository(Product::class)->find($id);
        self::assertSame('archive', $updated->getStatus());
        self::assertSame('Only Status', $updated->getName());
        self::assertSame('12', $updated->getSellingPrice());
        self::assertSame('kg', $updated->getUnit());
    }

    public function testCreateWithInvalidPayloadReturns422(): void
    {
        $this->removeProducts();

        $this->client->request('POST', '/api/product/', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Bad Image',
            'sku' => 'BAD-001',
            'unit' => 'kg',
            'sellingPrice' => '20',
            'costPrice' => '15',
            'status' => 'active',
            'imageUrl' => 'not-a-url',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
    }

    public function testUpdateWithInvalidPayloadReturns422(): void
    {
        $this->removeProducts();
        $product = $this->seedProduct('Valid Product', 'Screens');

        $this->client->request('PUT', '/api/product/'.$product->getId(), [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 'draft']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
    }
}
