<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use App\Entity\Notification;
use App\Entity\Setting;
use App\Enum\Channel;
use App\Enum\Severity;
use App\Repository\DeliveryAttemptRepository;
use App\Repository\DeliveryRepository;
use App\Repository\NotificationRepository;
use App\Service\SettingProvider;
use App\Utils\DemoUserUuids;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class PurgeNotificationsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private NotificationRepository $notifications;
    private DeliveryRepository $deliveries;
    private DeliveryAttemptRepository $attempts;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->notifications = $container->get(NotificationRepository::class);
        $this->deliveries = $container->get(DeliveryRepository::class);
        $this->attempts = $container->get(DeliveryAttemptRepository::class);

        $this->em->persist(new Setting('notifications.retention_days', '30', 'Retention in days'));
        $this->em->flush();
        $container->get(SettingProvider::class)->invalidate();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $this->tester = new CommandTester($application->find('app:notifications:purge'));
    }

    public function testPurgesOnlyArchivedNotificationsOlderThanRetention(): void
    {
        $oldArchived = $this->seed('Old archived', true);
        $delivery = new Delivery($oldArchived, Channel::EMAIL, 'jane@example.com', 'Subject', '<p>Body</p>');
        $delivery->markSending();
        $delivery->markSent();
        $this->em->persist($delivery);
        $this->em->persist(new DeliveryAttempt($delivery, 1, 'provider-1', null));
        $this->em->flush();

        $oldArchived->archive();
        $this->em->flush();
        $this->backdate($oldArchived, '-60 days');

        $recentArchived = $this->seed('Recent archived', true);
        $recentArchived->archive();
        $this->em->flush();

        $oldUnread = $this->seed('Old unread', false);
        $this->backdate($oldUnread, '-60 days');

        $this->tester->execute([]);
        $this->em->clear();

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Purged 1 notification(s).', $this->tester->getDisplay());
        self::assertNull($this->em->find(Notification::class, $oldArchived->getId()));
        self::assertNotNull($this->em->find(Notification::class, $recentArchived->getId()));
        self::assertNotNull($this->em->find(Notification::class, $oldUnread->getId()));
        self::assertSame(0, $this->deliveries->count([]));
        self::assertSame(0, $this->attempts->count([]));
        self::assertSame(2, $this->notifications->count([]));
    }

    private function seed(string $title, bool $backdateCreatedAt): Notification
    {
        $notification = new Notification(Uuid::fromString(DemoUserUuids::forNumber(1)), 'invoice.issued', $title, 'Body', Severity::INFO, true);
        $this->em->persist($notification);
        $this->em->flush();
        if ($backdateCreatedAt) {
            $this->backdate($notification, '-60 days', 'created_at');
        }

        return $notification;
    }

    /** SQLite has no setter for archived_at/created_at, so backdate in SQL. */
    private function backdate(Notification $notification, string $modifier, string $column = 'archived_at'): void
    {
        $date = new DateTimeImmutable($modifier)->format('Y-m-d H:i:s');
        $this->em->getConnection()->executeStatement(
            sprintf('UPDATE notifications SET %s = :date WHERE id = :id', $column),
            ['date' => $date, 'id' => $notification->getId()->toBinary()],
        );
    }
}
