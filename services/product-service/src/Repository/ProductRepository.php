<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Product> */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** Products behave as if they did not exist until they are persisted. */
    public function findActiveById(string $id): ?Product
    {
        return $this->find(Uuid::fromString($id));
    }

    /**
     * Full-text search belongs to the Search service: this is only a simple name filter.
     *
     * @return array{0: Product[], 1: int} items of the page, total number of matches
     */
    public function search(int $page, int $pageSize, ?string $search = null, ?string $categoryId = null, ?string $brandId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('b', 'u', 'c', 'v', 'm')
            ->join('p.brand', 'b')
            ->join('p.unit', 'u')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.productVariant', 'v')
            ->leftJoin('p.media', 'm')
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :search')
                ->setParameter('search', '%' . addcslashes(mb_strtolower($search), '%_\\') . '%');
        }
        if ($categoryId !== null) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', Uuid::fromString($categoryId), 'uuid');
        }
        if ($brandId !== null) {
            $qb->andWhere('b.id = :brandId')->setParameter('brandId', Uuid::fromString($brandId), 'uuid');
        }

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

        return [iterator_to_array($paginator), \count($paginator)];
    }
}
