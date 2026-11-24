<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServiceBootTest extends KernelTestCase
{
    public function testKernelBootsWithDoctrine(): void
    {
        self::bootKernel();
        self::assertInstanceOf(
            EntityManagerInterface::class,
            self::getContainer()->get(EntityManagerInterface::class)
        );
    }

    public function testMessengerTransportsAreWired(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        self::assertTrue($container->has('messenger.transport.notification_events'));
        self::assertTrue($container->has('messenger.transport.failed'));
    }
}
