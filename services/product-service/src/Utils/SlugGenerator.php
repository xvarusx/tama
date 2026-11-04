<?php

declare(strict_types=1);

namespace App\Utils;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * "Wireless Mice" -> "wireless-mice", and "wireless-mice-2" if the slug is already taken.
 * Works for any entity that has a `slug` field and an `id`.
 */
final class SlugGenerator
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @param class-string $entityClass @param int|null $ignoreId the entity being updated, so it does not clash with itself */
    public function generate(string $text, string $entityClass, ?int $ignoreId = null): string
    {
        // names with no latin letters (e.g. Arabic) need ext-intl to be transliterated; otherwise fall back
        $base = $this->slugger->slug($text)->lower()->toString() ?: 'item';

        $qb = $this->em->createQueryBuilder()
            ->select('e.slug')
            ->from($entityClass, 'e')
            ->where('e.slug = :base OR e.slug LIKE :like')
            ->setParameter('base', $base)
            ->setParameter('like', $base.'-%');

        if (null !== $ignoreId) {
            $qb->andWhere('e.id != :id')->setParameter('id', $ignoreId);
        }

        return Uniquify::make($base, $qb->getQuery()->getSingleColumnResult());
    }
}
