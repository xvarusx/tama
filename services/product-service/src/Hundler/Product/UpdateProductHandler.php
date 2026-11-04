<?php

declare(strict_types=1);

namespace App\Hundler\Product;

use App\Api\V1\Request\UpdateProductRequest;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Unit;
use App\Event\ProductUpdatedV1;
use App\Exceptions\ReferenceNotFoundException;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Use case: replace the product's own fields (name, description, brand, unit, category).
 * Variants and prices are not touched here.
 * The product change and its outbox event are saved in ONE transaction.
 */
final class UpdateProductHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    public function __invoke(int $id, UpdateProductRequest $request): Product
    {
        $product = $this->products->findActiveById($id)
            ?? throw new \RuntimeException(sprintf('Product %d does not exist.', $id));

        $brand = $this->em->find(Brand::class, $request->brandId)
            ?? throw new ReferenceNotFoundException('Brand', $request->brandId);
        $unit = $this->em->find(Unit::class, $request->unitId)
            ?? throw new ReferenceNotFoundException('Unit', $request->unitId);
        $category = null;
        if (null !== $request->categoryId) {
            $category = $this->em->find(Category::class, $request->categoryId)
                ?? throw new ReferenceNotFoundException('Category', $request->categoryId);
        }

        $changed = $this->changedFields($product, $request, $brand, $unit, $category);
        if ([] === $changed) {
            return $product; // nothing to save, so no write, no event, no cache flush
        }

        $product->setName($request->name)
            ->setDescription($request->description)
            ->setBrand($brand)
            ->setUnit($unit)
            ->setCategory($category);

        $this->em->wrapInTransaction(function () use ($product, $changed): void {
            $this->em->persist(ProductUpdatedV1::toOutbox($product, $changed));
            $this->em->flush(); // one flush = one transaction: the update and its event are saved together
        });

        $this->cache->invalidateTags(['products']);

        return $product;
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
