<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Product> */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** Products behave as if they did not exist until they are persisted. */
    public function findActiveById(int $id): ?Product
    {
        return $this->find($id);
    }

    /**
     * Full-text search belongs to the Search service: this is only a simple name filter.
     *
     * @return array{0: Product[], 1: int} items of the page, total number of matches
     */
    public function search(int $page, int $pageSize, ?string $search = null, ?int $categoryId = null, ?int $brandId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('b', 'u', 'c', 'v')
            ->join('p.brand', 'b')
            ->join('p.unit', 'u')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.productVariant', 'v')
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('LOWER(p.name) LIKE :search')
                ->setParameter('search', '%'.addcslashes(mb_strtolower($search), '%_\\').'%');
        }
        if (null !== $categoryId) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', $categoryId);
        }
        if (null !== $brandId) {
            $qb->andWhere('b.id = :brandId')->setParameter('brandId', $brandId);
        }

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

        return [iterator_to_array($paginator), \count($paginator)];
    }
}
