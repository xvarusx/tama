<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Setting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Exact keys read by SettingProvider, the delivery worker and retention (SDD ruling R6). */
final class SettingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $rows = [
            ['notifications.default_locale', 'en', 'Locale used to render every template'],
            ['notifications.retention_days', '30', 'Days to keep archived notifications before purging'],
            ['notifications.max_attempts', '3', 'Maximum send attempts per email delivery'],
            ['notifications.retry_base_seconds', '30', 'Base backoff in seconds for a temporary send failure'],
            ['notifications.retry_max_seconds', '300', 'Maximum backoff in seconds for a temporary send failure'],
            ['notifications.sending_timeout_seconds', '600', 'Seconds before a claimed delivery is reclaimed'],
            ['notifications.from_address', 'no-reply@example.com', 'From address for outgoing email'],
            ['notifications.from_name', 'ERP Notifications', 'From name for outgoing email'],
        ];

        foreach ($rows as [$key, $value, $description]) {
            $manager->persist(new Setting($key, $value, $description));
        }

        $manager->flush();
    }
}
