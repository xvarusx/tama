<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Setting;
use PHPUnit\Framework\TestCase;

final class SettingTest extends TestCase
{
    public function test_create_setting(): void
    {
        $setting = new Setting(
            'reminder_days_1',
            '1',
            'reminder_days',
            'Number of days for first payment reminder'
        );

        $this->assertSame('reminder_days_1', $setting->getKey());
        $this->assertSame('1', $setting->getValue());
        $this->assertSame('reminder_days', $setting->getType());
        $this->assertSame('Number of days for first payment reminder', $setting->getDescription());
    }

    public function test_set_value(): void
    {
        $setting = new Setting('reminder_days_1', '1', 'reminder_days');

        $setting->setValue('3');
        $this->assertSame('3', $setting->getValue());
    }

    public function test_set_description(): void
    {
        $setting = new Setting('key', 'value', 'type');

        $setting->setDescription('New description');
        $this->assertSame('New description', $setting->getDescription());
    }

    public function test_as_int(): void
    {
        $setting = new Setting('setting_1', '42', 'int');

        $this->assertSame(42, $setting->asInt());
    }

    public function test_as_bool_true(): void
    {
        $setting = new Setting('setting_1', 'true', 'bool');

        $this->assertTrue($setting->asBool());
    }

    public function test_as_bool_false(): void
    {
        $setting = new Setting('setting_1', 'false', 'bool');

        $this->assertFalse($setting->asBool());
    }

    public function test_as_bool_yes(): void
    {
        $setting = new Setting('setting_1', 'yes', 'bool');

        $this->assertTrue($setting->asBool());
    }

    public function test_as_bool_no(): void
    {
        $setting = new Setting('setting_1', 'no', 'bool');

        $this->assertFalse($setting->asBool());
    }

    public function test_without_description(): void
    {
        $setting = new Setting('key', 'value', 'type');

        $this->assertNull($setting->getDescription());
    }
}