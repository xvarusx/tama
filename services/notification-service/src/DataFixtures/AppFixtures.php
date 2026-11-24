<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use App\Entity\Notification;
use App\Entity\NotificationPreference;
use App\Entity\NotificationTemplate;
use App\Entity\ProcessedEvent;
use App\Entity\Setting;
use App\Enum\Channel;
use App\Enum\Severity;
use App\Utils\DemoUserUuids;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class AppFixtures extends Fixture
{
    private const int ROW_COUNT = 30;

    private const array NOTIFICATION_TYPES = [
        'order.created',
        'order.shipped',
        'order.delivered',
        'invoice.created',
        'invoice.overdue',
        'payment.received',
        'payment.failed',
        'stock.low',
        'user.welcome',
        'security.alert',
    ];

    private const array TEMPLATE_TYPES = [
        'order.created',
        'order.shipped',
        'invoice.created',
        'invoice.overdue',
        'payment.received',
        'payment.failed',
        'stock.low',
        'user.welcome',
        'security.alert',
        'password.reset',
    ];

    private const array TEMPLATE_LOCALES = ['en', 'fr', 'de'];

    private const array EVENT_SOURCES = ['order-service', 'payment-service', 'inventory-service'];

    public function load(ObjectManager $manager): void
    {
        // FK order: notifications -> deliveries -> delivery_attempts.
        $notifications = $this->loadNotifications($manager);
        $deliveries = $this->loadDeliveries($manager, $notifications);
        $this->loadDeliveryAttempts($manager, $deliveries);

        // Independent tables.
        $this->loadPreferences($manager);
        $this->loadTemplates($manager);
        $this->loadProcessedEvents($manager);
        $this->loadSettings($manager);
    }

    /** @return list<Notification> */
    private function loadNotifications(ObjectManager $manager): array
    {
        $notifications = [];
        $baseDate = new DateTimeImmutable('2026-01-01 08:00:00');

        for ($i = 0; $i < self::ROW_COUNT; ++$i) {
            $recipientNumber = 1 + ($i % 10);
            $notification = new Notification(
                recipientId: Uuid::fromString(DemoUserUuids::forNumber($recipientNumber)),
                type: self::NOTIFICATION_TYPES[$i % \count(self::NOTIFICATION_TYPES)],
                title: sprintf('Notification %03d', $i + 1),
                body: sprintf('This is the body of fixture notification %03d.', $i + 1),
                severity: Severity::cases()[$i % 4],
                visibleInApp: $i % 5 !== 0,
                link: $i % 2 === 0 ? sprintf('/notifications/%03d', $i + 1) : null,
                // Distinct dedup keys keep (recipient_id, dedup_key) unique.
                dedupKey: sprintf('dedup-%03d', $i + 1),
            );

            // Cycle through the three notification statuses via domain transitions.
            // UNREAD is the constructor default.
            if ($i % 3 === 1) {
                $notification->markAsRead();
            } elseif ($i % 3 === 2) {
                $notification->archive();
            }

            // The lifecycle callback fills updatedAt; fix createdAt for stable fixtures.
            $notification->setCreatedAt($baseDate->modify(sprintf('+%d days', $i)));

            $manager->persist($notification);
            $notifications[] = $notification;
        }

        $manager->flush();

        return $notifications;
    }

    /**
     * @param list<Notification> $notifications
     *
     * @return list<Delivery>
     */
    private function loadDeliveries(ObjectManager $manager, array $notifications): array
    {
        $deliveries = [];

        for ($i = 0; $i < self::ROW_COUNT; ++$i) {
            $notification = $notifications[$i];
            $channel = $i % 2 === 0 ? Channel::IN_APP : Channel::EMAIL;

            $delivery = new Delivery(
                notification: $notification,
                channel: $channel,
                // Matches the recipient numbering used in loadNotifications().
                recipientEmail: sprintf('recipient%03d@example.com', 1 + ($i % 10)),
                subject: sprintf('Delivery subject %03d', $i + 1),
                body: sprintf('Delivery body %03d', $i + 1),
            );

            // Cover every DeliveryStatus. QUEUED is the constructor default.
            switch ($i % 5) {
                case 1: // SENT
                    $delivery->markSending();
                    $delivery->markSent();
                    break;
                case 2: // RETRY_WAITING
                    $delivery->markSending();
                    $delivery->scheduleRetry(60, 'Temporary provider error');
                    break;
                case 3: // FAILED
                    $delivery->markSending();
                    $delivery->markFailed('Permanent provider error');
                    break;
                case 4: // SENDING
                    $delivery->markSending();
                    break;
            }

            $manager->persist($delivery);
            $deliveries[] = $delivery;
        }

        $manager->flush();

        return $deliveries;
    }

    /** @param list<Delivery> $deliveries */
    private function loadDeliveryAttempts(ObjectManager $manager, array $deliveries): void
    {
        for ($i = 0; $i < self::ROW_COUNT; ++$i) {
            $manager->persist(new DeliveryAttempt(
                delivery: $deliveries[$i],
                attemptNumber: 1,
                providerMessageId: null,
                error: null,
            ));
        }

        $manager->flush();
    }

    private function loadPreferences(ObjectManager $manager): void
    {
        for ($i = 0; $i < self::ROW_COUNT; ++$i) {
            // Two rows per user (IN_APP + EMAIL) keep (user_id, type, channel) unique.
            $manager->persist(new NotificationPreference(
                userId: Uuid::fromString(DemoUserUuids::forNumber(1 + intdiv($i, 2))),
                type: self::NOTIFICATION_TYPES[$i % \count(self::NOTIFICATION_TYPES)],
                channel: $i % 2 === 0 ? Channel::IN_APP : Channel::EMAIL,
                enabled: $i % 4 !== 0,
            ));
        }

        $manager->flush();
    }

    private function loadTemplates(ObjectManager $manager): void
    {
        $typeCount = \count(self::TEMPLATE_TYPES);
        $localeCount = \count(self::TEMPLATE_LOCALES);

        for ($i = 0; $i < self::ROW_COUNT; ++$i) {
            $type = self::TEMPLATE_TYPES[$i % $typeCount];
            $locale = self::TEMPLATE_LOCALES[intdiv($i, $typeCount) % $localeCount];

            // Each (type, locale) pair maps to exactly one channel -> unique triples.
            $manager->persist(new NotificationTemplate(
                type: $type,
                channel: $i % 2 === 0 ? Channel::IN_APP : Channel::EMAIL,
                locale: $locale,
                subject: sprintf('[%s] %s notification', strtoupper($locale), $type),
                bodyTemplate: sprintf('Hello {{ name }}, here is your %s notification (%s).', $type, $locale),
            ));
        }

        $manager->flush();
    }

    private function loadProcessedEvents(ObjectManager $manager): void
    {
        for ($i = 0; $i < self::ROW_COUNT; ++$i) {
            $manager->persist(new ProcessedEvent(
                eventId: (string)Uuid::v7(),
                source: self::EVENT_SOURCES[$i % \count(self::EVENT_SOURCES)],
            ));
        }

        $manager->flush();
    }

    private function loadSettings(ObjectManager $manager): void
    {
        for ($i = 0; $i < self::ROW_COUNT; ++$i) {
            $manager->persist(new Setting(
                key: sprintf('setting_%03d', $i + 1),
                value: sprintf('value_%03d', $i + 1),
                description: sprintf('Fixture setting number %d', $i + 1),
            ));
        }

        $manager->flush();
    }
}
