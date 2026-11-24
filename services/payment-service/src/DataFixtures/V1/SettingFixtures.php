<?php

declare(strict_types=1);

namespace App\DataFixtures\V1;

use App\Entity\Setting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SettingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $settings = [
            ['reminder_days_1', '1', 'reminder_days', 'Number of days for first payment reminder'],
            ['reminder_days_2', '3', 'reminder_days', 'Number of days for second payment reminder'],
            ['reminder_days_3', '7', 'reminder_days', 'Number of days for third payment reminder'],
            ['default_currency', 'USD', 'default_currency', 'Default currency for payments'],
            ['payment_prefix', 'PYMT', 'payment_prefix', 'Prefix for payment numbers'],
            ['invoice_prefix', 'INV', 'invoice_prefix', 'Prefix for invoice numbers'],
        ];

        foreach ($settings as [$key, $value, $type, $description]) {
            $setting = new Setting($key, $value, $type, $description);
            $manager->persist($setting);
            $this->addReference('setting-' . $key, $setting);
        }

        $manager->flush();
    }
}