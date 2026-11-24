<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class UserFixtures extends Fixture
{
    /**
     * Deterministic demo-user ids so contact-service (audit actors) and
     * notification-service (recipients/preferences) fixtures can reference
     * these accounts. Must stay in sync with DemoUserUuids in notification-service
     * and AppFixtures in contact-service.
     */
    private const string USER_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $user = (new User())
            ->setId($this->userId(1))
            ->setEmail('demo@example.com')
            ->setRoles(['ROLE_USER'])
            ->setFullName('Demo User')
            ->setIsVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'ChangeMe123!'));

        $manager->persist($user);

        $testUser = (new User())
            ->setId($this->userId(2))
            ->setEmail('test1@test.de')
            ->setRoles(['ROLE_USER'])
            ->setFullName('Test User')
            ->setIsVerified(true);
        $testUser->setPassword($this->passwordHasher->hashPassword($testUser, 'changeme123'));

        $manager->persist($testUser);

        // Verified demo accounts used by the notification-service event consumers
        // and the administrator UI. Password: ChangeMe123!
        $admin = (new User())
            ->setId($this->userId(3))
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setFullName('Administrator')
            ->setIsVerified(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'ChangeMe123!'));
        $manager->persist($admin);

        $accountant = (new User())
            ->setId($this->userId(4))
            ->setEmail('accountant@example.com')
            ->setRoles(['ROLE_ACCOUNTANT'])
            ->setFullName('Alice Accountant')
            ->setIsVerified(true);
        $accountant->setPassword($this->passwordHasher->hashPassword($accountant, 'ChangeMe123!'));
        $manager->persist($accountant);

        $manager->flush();
    }

    private function userId(int $number): Uuid
    {
        return Uuid::v5(Uuid::fromString(self::USER_NAMESPACE), 'erp-demo-user-' . $number);
    }
}
