<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    public function test_values(): void
    {
        $this->assertSame('PENDING', PaymentStatus::PENDING->value);
        $this->assertSame('COMPLETED', PaymentStatus::COMPLETED->value);
        $this->assertSame('FAILED', PaymentStatus::FAILED->value);
        $this->assertSame('CANCELED', PaymentStatus::CANCELED->value);
        $this->assertSame('REVERSED', PaymentStatus::REVERSED->value);
    }
}