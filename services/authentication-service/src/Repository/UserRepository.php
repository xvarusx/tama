<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Roles are stored as a JSON column, so filtering by role in SQL is not
     * portable across MySQL and SQLite. Filtering in PHP keeps the internal
     * user directory identical in development, CI and tests.
     *
     * @return list<User>
     */
    public function findVerifiedByRole(string $role): array
    {
        $normalized = str_starts_with($role, 'ROLE_') ? $role : 'ROLE_'.$role;

        return array_values(array_filter(
            $this->findAll(),
            static fn (User $user): bool => true === $user->isVerified()
                && \in_array($normalized, $user->getRoles(), true),
        ));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<User>
     */
    public function findVerifiedByIds(array $ids): array
    {
        $wanted = array_map(static fn (string $id): string => (string) Uuid::fromString($id), $ids);

        return array_values(array_filter(
            $this->findAll(),
            static fn (User $user): bool => true === $user->isVerified()
                && \in_array((string) $user->getId(), $wanted, true),
        ));
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
