<?php

declare(strict_types=1);

namespace App\Hundler\Product;

use App\Api\V1\Request\UpdateProductRequest;
use App\Api\V1\Request\UpdateVariantRequest;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Unit;
use App\Event\ProductUpdatedV1;
use App\Exceptions\DuplicateSkuException;
use App\Exceptions\ReferenceNotFoundException;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Use case: replace the product's fields and, when `variants` is present, sync
 * its variants (update by id, create without id, delete omitted ones).
 * The product change and its outbox event are saved in ONE transaction.
 */
final readonly class UpdateProductHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductRepository $products,
        private TagAwareCacheInterface $cache,
    ) {}

    public function __invoke(int $id, UpdateProductRequest $request): Product
    {
        $product = $this->products->findActiveById($id)
            ?? throw new RuntimeException(sprintf('Product %d does not exist.', $id));

        $brand = $this->em->find(Brand::class, $request->brandId)
            ?? throw new ReferenceNotFoundException('Brand', $request->brandId);
        $unit = $this->em->find(Unit::class, $request->unitId)
            ?? throw new ReferenceNotFoundException('Unit', $request->unitId);
        $category = null;
        if ($request->categoryId !== null) {
            $category = $this->em->find(Category::class, $request->categoryId)
                ?? throw new ReferenceNotFoundException('Category', $request->categoryId);
        }

        $changed = $this->changedFields($product, $request, $brand, $unit, $category);

        if ($request->variants !== null && $this->syncVariants($product, $request->variants)) {
            $changed[] = 'variants';
        }

        if ($changed === []) {
            return $product; // nothing to save, so no write, no event, no cache flush
        }

        $product->setName($request->name)
            ->setDescription($request->description)
            ->setBrand($brand)
            ->setUnit($unit)
            ->setCategory($category);

        try {
            $this->em->wrapInTransaction(function () use ($product, $changed): void {
                $this->em->persist(ProductUpdatedV1::toOutbox($product, $changed));
                $this->em->flush(); // one flush = one transaction: the update and its event are saved together
            });
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateSkuException(implode(', ', array_map(
                static fn(UpdateVariantRequest $v): string => $v->sku,
                $request->variants ?? [],
            )));
        }

        $this->cache->invalidateTags(['products']);

        return $product;
    }

    /**
     * @param UpdateVariantRequest[] $variants
     *
     * @return bool whether at least one variant was created, updated or deleted
     */
    private function syncVariants(Product $product, array $variants): bool
    {
        /** @var array<int, ProductVariant> $existing */
        $existing = [];
        foreach ($product->getProductVariant() as $variant) {
            $existing[(int)$variant->getId()] = $variant;
        }

        $seen = [];
        $submitted = [];
        foreach ($variants as $variant) {
            $sku = trim($variant->sku);
            $key = mb_strtolower($sku);

            if (isset($seen[$key])) {
                throw new DuplicateSkuException($sku);
            }
            $seen[$key] = $sku;

            if ($variant->id !== null) {
                if (!isset($existing[$variant->id])) {
                    throw new ReferenceNotFoundException('Variant', $variant->id);
                }
                $submitted[$variant->id] = true;
            }
        }

        $this->assertSkusAreFree(array_values($seen), $product);

        $changed = false;

        foreach ($variants as $variant) {
            $sku = trim($variant->sku);

            if ($variant->id === null) {
                $product->addProductVariant(new ProductVariant(
                    $sku,
                    $variant->costPrice,
                    $variant->sellingPrice,
                    $variant->barcode,
                    $variant->options,
                ));
                $changed = true;

                continue;
            }

            $current = $existing[$variant->id];

            if ($current->getSku() !== $sku) {
                $current->setSku($sku);
                $changed = true;
            }
            if ($current->getCostPrice() !== $variant->costPrice) {
                $current->setCostPrice($variant->costPrice);
                $changed = true;
            }
            if ($current->getSellingPrice() !== $variant->sellingPrice) {
                $current->setSellingPrice($variant->sellingPrice);
                $changed = true;
            }
            if ($current->getBarcode() !== $variant->barcode) {
                $current->setBarcode($variant->barcode);
                $changed = true;
            }
            if ($current->getOptions() !== $variant->options) {
                $current->setOptions($variant->options);
                $changed = true;
            }
        }

        foreach ($existing as $variantId => $variant) {
            if (!isset($submitted[$variantId])) {
                $product->removeProductVariant($variant);
                $changed = true;
            }
        }

        return $changed;
    }

    /** @param string[] $skus */
    private function assertSkusAreFree(array $skus, Product $product): void
    {
        if ($skus === []) {
            return;
        }

        $existing = $this->em
            ->createQuery('SELECT v FROM ' . ProductVariant::class . ' v WHERE v.sku IN (:skus) AND v.product != :product')
            ->setParameter('skus', $skus)
            ->setParameter('product', $product)
            ->setMaxResults(1)
            ->getResult();

        if ($existing !== []) {
            throw new DuplicateSkuException((string)$existing[0]->getSku());
        }
    }

    /** @return string[] names of the fields that really differ */
    private function changedFields(Product $product, UpdateProductRequest $request, Brand $brand, Unit $unit, ?Category $category): array
    {
        $changed = [];

        if ($product->getName() !== $request->name) {
            $changed[] = 'name';
        }
        if ($product->getDescription() !== $request->description) {
            $changed[] = 'description';
        }
        if ($product->getBrand()->getId() !== $brand->getId()) {
            $changed[] = 'brand';
        }
        if ($product->getUnit()->getId() !== $unit->getId()) {
            $changed[] = 'unit';
        }
        if ($product->getCategory()?->getId() !== $category?->getId()) {
            $changed[] = 'category';
        }

        return $changed;
    }
}
