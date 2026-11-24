<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ReminderKind;
use PHPUnit\Framework\TestCase;

final class ReminderKindTest extends TestCase
{
    public function test_values(): void
    {
        $this->assertSame('UPCOMING_DUE', ReminderKind::UPCOMING_DUE->value);
        $this->assertSame('OVERDUE', ReminderKind::OVERDUE->value);
    }
}