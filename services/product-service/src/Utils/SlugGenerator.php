<?php

declare(strict_types=1);

namespace App\Utils;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * "Wireless Mice" -> "wireless-mice", and "wireless-mice-2" if the slug is already taken.
 * Works for any entity that has a `slug` field and an `id`.
 */
final readonly class SlugGenerator
{
    public function __construct(
        private SluggerInterface $slugger,
        private EntityManagerInterface $em,
    ) {}

    /** @param class-string $entityClass @param string|null $ignoreId UUID of the entity being updated, so it does not clash with itself */
    public function generate(string $text, string $entityClass, ?string $ignoreId = null): string
    {
        // names with no latin letters (e.g. Arabic) need ext-intl to be transliterated; otherwise fall back
        $base = $this->slugger->slug($text)->lower()->toString() ?: 'item';

        $qb = $this->em->createQueryBuilder()
            ->select('e.slug')
            ->from($entityClass, 'e')
            ->where('e.slug = :base OR e.slug LIKE :like')
            ->setParameter('base', $base)
            ->setParameter('like', $base . '-%');

        if ($ignoreId !== null) {
            $qb->andWhere('e.id != :id')->setParameter('id', Uuid::fromString($ignoreId), 'uuid');
        }

        return Uniquify::make($base, $qb->getQuery()->getSingleColumnResult());
    }
}
