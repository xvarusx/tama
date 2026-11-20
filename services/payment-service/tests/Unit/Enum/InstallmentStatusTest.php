<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\InstallmentStatus;
use PHPUnit\Framework\TestCase;

final class InstallmentStatusTest extends TestCase
{
    public function test_values(): void
    {
        $this->assertSame('UNPAID', InstallmentStatus::UNPAID->value);
        $this->assertSame('PARTIALLY_PAID', InstallmentStatus::PARTIALLY_PAID->value);
        $this->assertSame('PAID', InstallmentStatus::PAID->value);
        $this->assertSame('CANCELED', InstallmentStatus::CANCELED->value);
    }
}