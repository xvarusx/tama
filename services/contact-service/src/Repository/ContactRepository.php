<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Enum\ContactStatus;
use App\Enum\ContactType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * @return array{items: Contact[], total: int}
     */
    public function findPaginated(
        int $page,
        int $pageSize,
        ?string $search = null,
        ?ContactType $type = null,
        ?ContactStatus $status = null,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($type !== null) {
            $qb->andWhere('c.contactType = :type')
                ->setParameter('type', $type);
        }

        if ($status !== null) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $adapter = new QueryAdapter($qb->getQuery());
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($pageSize);
        $pager->setCurrentPage($page);

        $items = iterator_to_array($pager->getCurrentPageResults());

        return ['items' => $items, 'total' => $total];
    }
}
