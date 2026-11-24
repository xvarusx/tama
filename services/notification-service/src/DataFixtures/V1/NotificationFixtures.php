<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Delivery;
use App\Entity\Notification;
use App\Enum\Channel;
use App\Enum\Severity;
use App\Utils\DemoUserUuids;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/** A small inbox for the demo user so the frontend can be exercised immediately. */
final class NotificationFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $recipientId = Uuid::fromString(DemoUserUuids::forNumber(1));
        $rows = [
            ['payment.installment.overdue', 'Invoice F-012 overdue', 'Invoice F-012 for 20000 TND is overdue.', Severity::WARNING, true, '/dashboard/invoices/F-012', 'unread'],
            ['invoice.issued', 'Invoice F-013 issued', 'Invoice F-013 for 15000 TND has been issued.', Severity::INFO, true, '/dashboard/invoices/F-013', 'unread'],
            ['order.confirmed', 'Order ORD-0042 confirmed', 'Your order ORD-0042 has been confirmed.', Severity::SUCCESS, true, '/dashboard/orders/ORD-0042', 'read'],
            ['payment.installment.overdue', 'Invoice F-011 overdue', 'Invoice F-011 for 8000 TND is overdue.', Severity::CRITICAL, true, null, 'archived'],
            ['invoice.issued', 'Invoice F-010 issued', 'Invoice F-010 for 5000 TND has been issued.', Severity::INFO, true, null, 'read'],
            ['order.confirmed', 'Order ORD-0041 confirmed', 'Your order ORD-0041 has been confirmed.', Severity::SUCCESS, false, null, 'unread'],
        ];

        foreach ($rows as $index => [$type, $title, $body, $severity, $visibleInApp, $link, $status]) {
            $notification = new Notification(
                recipientId: $recipientId,
                type: $type,
                title: $title,
                body: $body,
                severity: $severity,
                visibleInApp: $visibleInApp,
                link: $link,
                dedupKey: sprintf('demo-notification-%03d', $index + 1),
            );

            if ($status === 'read') {
                $notification->markAsRead();
            } elseif ($status === 'archived') {
                $notification->archive();
            }

            $manager->persist($notification);

            if ($index === 0) {
                $delivery = new Delivery(
                    notification: $notification,
                    channel: Channel::EMAIL,
                    recipientEmail: 'demo@example.com',
                    subject: $title,
                    body: sprintf('<p>%s</p>', $body),
                );
                $delivery->markSending();
                $delivery->markSent();
                $manager->persist($delivery);
            }

            if ($index === 1) {
                $manager->persist(new Delivery(
                    notification: $notification,
                    channel: Channel::EMAIL,
                    recipientEmail: 'demo@example.com',
                    subject: $title,
                    body: sprintf('<p>%s</p>', $body),
                ));
            }
        }

        $manager->flush();
    }
}
