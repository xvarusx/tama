<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\NotificationPreference;
use App\Enum\Channel;
use App\Utils\DemoUserUuids;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/** One explicit opt-out; every missing preference row means "enabled". */
final class PreferenceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->persist(new NotificationPreference(
            userId: Uuid::fromString(DemoUserUuids::forNumber(1)),
            type: 'payment.installment.overdue',
            channel: Channel::IN_APP,
            enabled: false,
        ));

        $manager->flush();
    }
}
