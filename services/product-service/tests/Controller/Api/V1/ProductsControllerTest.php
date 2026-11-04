<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Unit;
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
    }

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get('doctrine')->getManager();
    }

    private function seedBrand(string $name = 'Acme'): Brand
    {
        $em = $this->em();
        $brand = new Brand($name, strtolower($name));
        $em->persist($brand);
        $em->flush();

        return $brand;
    }

    private function seedUnit(string $code = 'kg'): Unit
    {
        $em = $this->em();
        $unit = new Unit($code, true);
        $em->persist($unit);
        $em->flush();

        return $unit;
    }

    private function seedCategory(string $name = 'Screens'): Category
    {
        $em = $this->em();
        $category = new Category($name, strtolower($name));
        $em->persist($category);
        $em->flush();

        return $category;
    }

    private function seedProduct(string $name = 'Alpha Product'): Product
    {
        $em = $this->em();
        $brand = $this->seedBrand('Brand Alpha');
        $unit = $this->seedUnit('kg');
        $category = $this->seedCategory('Category Alpha');

        $product = new Product($name, $brand, $unit);
        $product->setCategory($category);
        $product->setDescription('Description');

        $variant = new ProductVariant('SKU-001', '10.00', '12.00');
        $product->addProductVariant($variant);

        $em->persist($product);
        $em->flush();

        return $product;
    }

    public function testIndexListProducts(): void
    {
        $product = $this->seedProduct('Alpha Product');

        $this->client->request('GET', '/api/v1/products');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('success', $response['status']);
        self::assertCount(1, $response['data']);
        self::assertSame($product->getId(), $response['data'][0]['id']);
        self::assertSame('Alpha Product', $response['data'][0]['attributes']['name']);
        self::assertSame('Category Alpha', $response['data'][0]['attributes']['category']['name']);
    }

    public function testShowByIdReturnsProduct(): void
    {
        $product = $this->seedProduct('Gamma Product');

        $this->client->request('GET', '/api/v1/products/'.$product->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('success', $response['status']);
        self::assertSame($product->getId(), $response['data']['id']);
        self::assertSame('Gamma Product', $response['data']['attributes']['name']);
    }

    public function testShowMissingProductReturns404(): void
    {
        $this->client->request('GET', '/api/v1/products/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateProduct(): void
    {
        $brand = $this->seedBrand('Test Brand');
        $unit = $this->seedUnit('pcs');
        $category = $this->seedCategory('Test Category');

        $this->client->request('POST', '/api/v1/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'New Product',
            'brandId' => $brand->getId(),
            'unitId' => $unit->getId(),
            'categoryId' => $category->getId(),
            'description' => 'A new product',
            'variants' => [
                [
                    'sku' => 'TEST-SKU-001',
                    'costPrice' => '10.00',
                    'sellingPrice' => '15.00',
                ],
            ],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('success', $response['status']);
        self::assertSame('New Product', $response['data']['attributes']['name']);
        self::assertNotNull($response['data']['id']);
    }

    public function testUpdateProduct(): void
    {
        $product = $this->seedProduct('Old Name');

        $this->client->request('PUT', '/api/v1/products/'.$product->getId(), [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Renamed Product',
            'brandId' => $product->getBrand()->getId(),
            'unitId' => $product->getUnit()->getId(),
            'description' => 'Updated description',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('success', $response['status']);
        self::assertSame('Renamed Product', $response['data']['attributes']['name']);
    }

    public function testCreateWithUnknownBrandReturns422(): void
    {
        $unit = $this->seedUnit('pcs');

        $this->client->request('POST', '/api/v1/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Ghost Product',
            'brandId' => 999999,
            'unitId' => $unit->getId(),
            'variants' => [['sku' => 'GHOST-1', 'costPrice' => '1.00', 'sellingPrice' => '2.00']],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('error', $body['status']);
        self::assertSame('REFERENCE_NOT_FOUND', $body['error']['code']);
    }

    public function testCreateWithDuplicateSkuReturns409(): void
    {
        $this->seedProduct('Existing'); // seeds variant SKU-001
        $brand = $this->seedBrand('Second Brand');
        $unit = $this->seedUnit('pcs');

        $this->client->request('POST', '/api/v1/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Clashing Product',
            'brandId' => $brand->getId(),
            'unitId' => $unit->getId(),
            'variants' => [['sku' => 'SKU-001', 'costPrice' => '1.00', 'sellingPrice' => '2.00']],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('SKU_ALREADY_EXISTS', $body['error']['code']);
    }

    public function testCreateProductWithMultipleVariants(): void
    {
        $brand = $this->seedBrand('Variant Brand');
        $unit = $this->seedUnit('pcs');

        $this->client->request('POST', '/api/v1/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'T-Shirt',
            'brandId' => $brand->getId(),
            'unitId' => $unit->getId(),
            'variants' => [
                ['sku' => 'TSHIRT-S', 'costPrice' => '8.00', 'sellingPrice' => '15.00', 'barcode' => '111'],
                ['sku' => 'TSHIRT-M', 'costPrice' => '8.00', 'sellingPrice' => '15.00'],
                ['sku' => 'TSHIRT-L', 'costPrice' => '9.50', 'sellingPrice' => '17.50'],
            ],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        $variants = $body['data']['attributes']['variants'];

        self::assertCount(3, $variants);
        self::assertSame(['TSHIRT-S', 'TSHIRT-M', 'TSHIRT-L'], array_column($variants, 'sku'));
        self::assertSame('17.50', $variants[2]['sellingPrice']);
    }

    public function testDeleteProduct(): void
    {
        $product = $this->seedProduct('Delete Me');

        $this->client->request('DELETE', '/api/v1/products/'.$product->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $em = $this->em();
        $em->clear();
        // Use query instead of find() due to Doctrine ORM 3 bug with find() after cascade delete
        $found = $em->createQuery('SELECT p FROM App\Entity\Product p WHERE p.id = :id')
            ->setParameter('id', $product->getId())
            ->getOneOrNullResult();
        self::assertNull($found, 'Product should be deleted but was found: '.($found?->getId() ?? 'null'));
    }
}
