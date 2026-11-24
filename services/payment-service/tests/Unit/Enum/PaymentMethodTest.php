<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class PaymentMethodTest extends TestCase
{
    public function test_values(): void
    {
        $this->assertSame('CASH', PaymentMethod::CASH->value);
        $this->assertSame('CHEQUE', PaymentMethod::CHEQUE->value);
        $this->assertSame('BANK_TRANSFER', PaymentMethod::BANK_TRANSFER->value);
        $this->assertSame('OTHER', PaymentMethod::OTHER->value);
    }
}