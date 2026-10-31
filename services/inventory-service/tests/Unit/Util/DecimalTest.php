<?php

namespace App\Tests\Unit\Util;

use App\Util\Decimal;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function testFormatRoundsToThreePlaces(): void
    {
        self::assertSame('12.346', Decimal::format(12.3456));
        self::assertSame('100.500', Decimal::format('100.5'));
        self::assertSame('0.000', Decimal::format(0));
    }

    public function testSubtractKeepsThreePlaces(): void
    {
        self::assertSame('79.500', Decimal::subtract('100.000', '20.500'));
        self::assertSame('0.001', Decimal::subtract('100.000', '99.999'));
    }

    public function testSubtractCanGoNegative(): void
    {
        self::assertSame('-5.000', Decimal::subtract('5.000', '10.000'));
    }

    public function testCompareNormalizesScale(): void
    {
        self::assertSame(0, Decimal::compare('10.000', '10.0'));
        self::assertSame(-1, Decimal::compare('9.999', '10.000'));
        self::assertSame(1, Decimal::compare('10.001', '10.000'));
    }

    public function testFormatAcceptsTheDecimalRangeBoundaries(): void
    {
        self::assertSame('999999999.999', Decimal::format('999999999.999'));
        self::assertSame('-999999999.999', Decimal::format('-999999999.999'));
    }

    public function testFormatRejectsValuesOutsideTheDecimalRange(): void
    {
        $values = ['1000000000', '-1000000000', '1e400', '999999999.9995', \INF, \NAN];

        foreach ($values as $value) {
            try {
                Decimal::format($value);
                self::fail(sprintf('Expected "%s" to be rejected.', (string) $value));
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }
}
