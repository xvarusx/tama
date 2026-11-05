<?php

declare(strict_types=1);

namespace App\Utils;

use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Generates a SKU like "BRAND-product-name-M" from a brand name, a product
 * name and the variant's options. The result is uniquified against the
 * already-chosen SKUs in the same request and the rows already in the DB.
 */
final readonly class SkuGenerator
{
    public function __construct(
        private SluggerInterface $slugger,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @param array<string, mixed>|null $options e.g. ['size' => 'M', 'color' => 'black']
     * @param string[]|null             $taken   SKUs already chosen in the current request
     */
    public function generate(
        string $brandName,
        string $productName,
        ?array $options = null,
        ?array $taken = null,
    ): string {
        $parts = [
            $this->slugger->slug($brandName)->lower()->toString(),
            $this->slugger->slug($productName)->lower()->toString(),
        ];

        if (is_array($options) && $options !== []) {
            $parts[] = implode('-', array_map(
                fn(string $key, mixed $value): string => $this->slugger->slug((string)$key . '-' . (string)$value)->lower()->toString(),
                array_keys($options),
                array_values($options),
            ));
        }

        $base = implode('-', array_filter($parts, static fn(string $p): bool => $p !== ''));

        if ($base === '') {
            $base = 'item';
        }

        $taken ??= [];

        $qb = $this->em->createQueryBuilder()
            ->select('v.sku')
            ->from(ProductVariant::class, 'v')
            ->where('v.sku = :base OR v.sku LIKE :like')
            ->setParameter('base', $base)
            ->setParameter('like', $base . '-%');

        $existing = $qb->getQuery()->getSingleColumnResult();

        return Uniquify::make($base, array_merge($taken, $existing));
    }
}
