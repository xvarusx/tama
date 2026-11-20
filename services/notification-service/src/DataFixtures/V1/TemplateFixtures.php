<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\NotificationTemplate;
use App\Enum\Channel;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Templates for the event types producer services publish today. */
final class TemplateFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $rows = [
            [
                'payment.installment.overdue',
                'Invoice {{ invoiceNumber }} overdue',
                'Invoice {{ invoiceNumber }} for {{ amount }} {{ currency }} is overdue.',
                '<p>Hello {{ partyName }},</p><p>Invoice {{ invoiceNumber }} for {{ amount }} {{ currency }} is overdue. Please settle it at your earliest convenience.</p>',
            ],
            [
                'invoice.issued',
                'Invoice {{ invoiceNumber }} issued',
                'Invoice {{ invoiceNumber }} for {{ amount }} {{ currency }} has been issued.',
                '<p>Hello {{ partyName }},</p><p>Invoice {{ invoiceNumber }} for {{ amount }} {{ currency }} is now available.</p>',
            ],
            [
                'order.confirmed',
                'Order {{ orderNumber }} confirmed',
                'Your order {{ orderNumber }} has been confirmed.',
                '<p>Hello {{ partyName }},</p><p>Your order {{ orderNumber }} has been confirmed. Thank you for your business.</p>',
            ],
            [
                'generic',
                '{{ type }}',
                '{{ message }}',
                '<p>{{ message }}</p>',
            ],
        ];

        foreach ($rows as [$type, $subject, $inAppBody, $emailBody]) {
            $manager->persist(new NotificationTemplate($type, Channel::IN_APP, 'en', $subject, $inAppBody));
            $manager->persist(new NotificationTemplate($type, Channel::EMAIL, 'en', $subject, $emailBody));
        }

        $manager->flush();
    }
}
