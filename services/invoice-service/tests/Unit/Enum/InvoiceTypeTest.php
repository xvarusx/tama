<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\InvoiceType;
use PHPUnit\Framework\TestCase;

final class InvoiceTypeTest extends TestCase
{
    public function testNumberPrefixes(): void
    {
        self::assertSame('INV', InvoiceType::SALES->numberPrefix());
        self::assertSame('BILL', InvoiceType::PURCHASE->numberPrefix());
        self::assertSame('sales', InvoiceType::SALES->value);
        self::assertSame('purchase', InvoiceType::PURCHASE->value);
    }
}
