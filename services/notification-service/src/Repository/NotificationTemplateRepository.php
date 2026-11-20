<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationTemplate;
use App\Enum\Channel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<NotificationTemplate>
 */
class NotificationTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationTemplate::class);
    }

    public function findByTypeChannelLocale(string $type, Channel $channel, string $locale): ?NotificationTemplate
    {
        return $this->createQueryBuilder('t')
            ->where('t.type = :type AND t.channel = :channel AND t.locale = :locale')
            ->setParameter('type', $type)
            ->setParameter('channel', $channel->value)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findById(Uuid $id): ?NotificationTemplate
    {
        return $this->find($id);
    }

    /**
     * Distinct notification types used to build the preference matrix.
     *
     * @return list<string>
     */
    public function findDistinctTypes(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('DISTINCT t.type')
            ->orderBy('t.type', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn(array $row): string => (string)$row['type'], $rows);
    }
}
