<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Dto\NotificationFilter;
use App\Entity\Notification;
use App\Entity\Setting;
use App\Enum\NotificationStatus;
use App\Enum\Severity;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Utils\DemoUserUuids;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class NotificationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private NotificationRepository $notifications;
    private SettingRepository $settings;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->notifications = self::getContainer()->get(NotificationRepository::class);
        $this->settings = self::getContainer()->get(SettingRepository::class);

        // Create schema in memory
        $configuration = $this->em->getConfiguration();
        $configuration->setUseSchemaEditorApi(true);
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema([
            $this->em->getClassMetadata(Notification::class),
            $this->em->getClassMetadata(Setting::class),
        ]);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    public function testInboxSearchFiltersHiddenItemsAndCountsUnread(): void
    {
        // Seed two visible notifications (one unread) and one visibleInApp=false notification
        $jane = DemoUserUuids::forNumber(1);
        $notification1 = new Notification(
            Uuid::fromString($jane),
            'payment.installment.overdue',
            'Invoice F-001 overdue',
            'Please settle invoice F-001.',
            Severity::WARNING,
            true,
            '/dashboard/invoices/F-001',
            null,
        );
        $notification2 = new Notification(
            Uuid::fromString($jane),
            'payment.installment.overdue',
            'Invoice F-002 overdue',
            'Please settle invoice F-002.',
            Severity::WARNING,
            true,
            '/dashboard/invoices/F-002',
            null,
        );
        // Set the second notification as read
        $notification2->markAsRead();

        // This notification should be hidden from inbox
        $notification3 = new Notification(
            Uuid::fromString($jane),
            'payment.installment.overdue',
            'Invoice F-003 overdue (email only)',
            'Please settle invoice F-003.',
            Severity::WARNING,
            false, // visibleInApp = false
            '/dashboard/invoices/F-003',
            null,
        );

        $this->em->persist($notification1);
        $this->em->persist($notification2);
        $this->em->persist($notification3);
        $this->em->flush();

        // Test searchForRecipient returns only visible notifications
        [$results, $totalItems] = $this->notifications->searchForRecipient($jane, new NotificationFilter());

        self::assertCount(2, $results);
        self::assertSame(2, $totalItems);

        // Test countUnread is 1 (only notification1 is unread, notification2 is read)
        $unreadCount = $this->notifications->countUnread($jane);
        self::assertSame(1, $unreadCount);

        // Test status filter
        [$unreadResults, $unreadTotal] = $this->notifications->searchForRecipient(
            $jane,
            new NotificationFilter(status: NotificationStatus::UNREAD)
        );
        self::assertCount(1, $unreadResults);
        self::assertSame(1, $unreadTotal);
    }

    public function testDedupKeyConflictIsDetectable(): void
    {
        // Create a notification with a dedup key
        $john = DemoUserUuids::forNumber(2);
        $notification1 = new Notification(
            Uuid::fromString($john), // Different user to avoid conflicts
            'payment.installment.overdue',
            'Invoice D-001 overdue',
            'Please settle invoice D-001.',
            Severity::WARNING,
            true,
            '/dashboard/invoices/D-001',
            'installment-abc123:2026-11-15',
        );

        $this->em->persist($notification1);
        $this->em->flush();

        // Verify the dedup key is stored
        self::assertSame('installment-abc123:2026-11-15', $notification1->getDedupKey());

        // Verify unique constraint exists (trying to insert a duplicate would fail)
        $notification2 = new Notification(
            Uuid::fromString($john), // Same user
            'payment.installment.overdue',
            'Invoice D-001 duplicate',
            'Please settle invoice D-001.',
            Severity::WARNING,
            true,
            '/dashboard/invoices/D-001',
            'installment-abc123:2026-11-15', // Same dedup key
        );

        $this->em->persist($notification2);

        // Flush should throw a unique constraint violation
        try {
            $this->em->flush();
            self::fail('Expected unique constraint violation for duplicate dedup key');
        } catch (UniqueConstraintViolationException $e) {
            // Expected - unique constraint violation
            self::assertStringContainsString('notifications.recipient_id, notifications.dedup_key', $e->getMessage());
        } finally {
            // Clean up
            $this->em->clear();
        }
    }

    public function testSettingGetFallsBackToDefault(): void
    {
        // Test that get() returns default when setting doesn't exist
        $defaultLocale = $this->settings->get('notifications.default_locale', 'en');
        self::assertSame('en', $defaultLocale);

        // Create a setting
        $setting = new Setting(
            'notifications.default_locale',
            'fr',
            'Default locale for notifications'
        );
        $this->em->persist($setting);
        $this->em->flush();

        // Test that get() returns the actual value
        $locale = $this->settings->get('notifications.default_locale', 'en');
        self::assertSame('fr', $locale);

        // Test findByKey returns the setting
        $foundSetting = $this->settings->findByKey('notifications.default_locale');
        self::assertNotNull($foundSetting);
        self::assertSame('fr', $foundSetting->getValue());
    }
}
