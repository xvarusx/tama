<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProcessedEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProcessedEvent>
 */
class ProcessedEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedEvent::class);
    }

    /** Transport-level dedup: the primary key is the concurrency guard. */
    public function exists(string $eventId): bool
    {
        return $this->find(Uuid::fromString($eventId)) !== null;
    }
}
