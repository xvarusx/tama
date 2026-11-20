<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\PaymentDirection;
use PHPUnit\Framework\TestCase;

final class PaymentDirectionTest extends TestCase
{
    public function test_values(): void
    {
        $this->assertSame('INCOMING', PaymentDirection::INCOMING->value);
        $this->assertSame('OUTGOING', PaymentDirection::OUTGOING->value);
    }
}