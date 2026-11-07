<?php

declare(strict_types=1);

namespace App\Hundler\Product;

use App\Api\V1\Request\CreateProductRequest;
use App\Api\V1\Request\CreateVariantRequest;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Unit;
use App\Event\ProductCreatedV1;
use App\Exceptions\DuplicateSkuException;
use App\Exceptions\ReferenceNotFoundException;
use App\Utils\SkuGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Use case: create a product with its variants.
 * The product and its outbox event are saved in ONE transaction.
 */
final readonly class CreateProductHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private TagAwareCacheInterface $cache,
        private SkuGenerator $skus,
    ) {}

    public function __invoke(CreateProductRequest $request): Product
    {
        $brand = $this->em->find(Brand::class, $request->brandId)
            ?? throw new ReferenceNotFoundException('Brand', $request->brandId);
        $unit = $this->em->find(Unit::class, $request->unitId)
            ?? throw new ReferenceNotFoundException('Unit', $request->unitId);
        $category = null;
        if ($request->categoryId !== null) {
            $category = $this->em->find(Category::class, $request->categoryId)
                ?? throw new ReferenceNotFoundException('Category', $request->categoryId);
        }

        $skus = array_map(static fn(CreateVariantRequest $v): string => $v->sku, $request->variants);
        $this->assertSkusAreFree($skus);

        $product = new Product($request->name, $brand, $unit);
        $product->setDescription($request->description);
        $product->setCategory($category);
        $taken = [];
        foreach ($request->variants as $v) {
            $sku = $v->sku !== ''
                ? $v->sku
                : $this->skus->generate($brand->getName(), $request->name, $v->options, $taken);
            $taken[] = $sku;
            $product->addProductVariant(new ProductVariant($sku, $v->costPrice, $v->sellingPrice, $v->barcode, $v->options));
        }

        try {
            $this->em->wrapInTransaction(function () use ($product): void {
                $this->em->persist($product);
                $this->em->flush(); // generates the ids needed by the event payload

                $this->em->persist(ProductCreatedV1::toOutbox($product));
                $this->em->flush();
            });
        } catch (UniqueConstraintViolationException) {
            // two requests created the same SKU at the same moment
            throw new DuplicateSkuException(implode(', ', $skus));
        }

        $this->cache->invalidateTags(['products']);

        return $product;
    }

    /** @param string[] $skus */
    private function assertSkusAreFree(array $skus): void
    {
        $duplicatesInPayload = array_diff_assoc($skus, array_unique($skus));
        if ($duplicatesInPayload !== []) {
            throw new DuplicateSkuException((string) reset($duplicatesInPayload));
        }

        $existing = $this->em
            ->createQuery('SELECT v FROM ' . ProductVariant::class . ' v WHERE v.sku IN (:skus)')
            ->setParameter('skus', $skus)
            ->setMaxResults(1)
            ->getResult();

        if ($existing !== []) {
            throw new DuplicateSkuException((string) $existing[0]->getSku());
        }
    }
}
