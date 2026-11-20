<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Setting;
use App\Service\SettingProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettingProviderTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SettingProvider $settings;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->settings = self::getContainer()->get(SettingProvider::class);

        $configuration = $this->em->getConfiguration();
        $configuration->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema([
            $this->em->getClassMetadata(Setting::class),
        ]);

        $this->em->persist(new Setting('notifications.default_locale', 'fr', 'Default locale'));
        $this->em->persist(new Setting('notifications.sending_timeout_seconds', '120', 'Timeout'));
        $this->em->flush();
        $this->settings->invalidate();
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    public function testReadsStringAndIntSettings(): void
    {
        self::assertSame('fr', $this->settings->string('notifications.default_locale'));
        self::assertSame(120, $this->settings->int('notifications.sending_timeout_seconds'));
    }

    public function testFallsBackToCodeDefaults(): void
    {
        self::assertSame(3, $this->settings->int('notifications.max_attempts'));
        self::assertSame('no-reply@example.com', $this->settings->string('notifications.from_address'));
        self::assertSame('', $this->settings->string('unknown.key'));
        self::assertSame(0, $this->settings->int('unknown.key'));
    }

    public function testInvalidateRefreshesCachedValues(): void
    {
        self::assertSame('fr', $this->settings->string('notifications.default_locale'));

        $setting = $this->em->getRepository(Setting::class)->findOneBy([
            'key' => 'notifications.default_locale',
        ]);
        self::assertInstanceOf(Setting::class, $setting);
        $setting->setValue('de');
        $this->em->flush();

        // Still cached until invalidated.
        self::assertSame('fr', $this->settings->string('notifications.default_locale'));

        $this->settings->invalidate();

        self::assertSame('de', $this->settings->string('notifications.default_locale'));
    }
}
