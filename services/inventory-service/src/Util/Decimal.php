<?php

namespace App\Util;

/**
 * Exact arithmetic for stock values expressed with three decimals.
 *
 * Values are parsed into integer thousandths so that subtraction and
 * comparison never drift, as they would with binary floating point.
 */
final class Decimal
{
    private const int SCALE = 1000;

    /**
     * Largest magnitude that fits a DECIMAL(12, 3) column.
     */
    public const string MAX_MAGNITUDE = '999999999.999';

    public static function format(string|int|float $value): string
    {
        $float = (float) $value;

        if (!is_finite($float)) {
            throw new \InvalidArgumentException(sprintf('Decimal value %s is not finite.', var_export($value, true)));
        }

        $formatted = number_format($float, 3, '.', '');

        // Compare the rounded output: 999999999.9994 fits, 999999999.9995 does not.
        if (abs((float) $formatted) > (float) self::MAX_MAGNITUDE) {
            throw new \InvalidArgumentException(sprintf('Decimal value %s is outside the DECIMAL(12, 3) range.', var_export($value, true)));
        }

        return $formatted;
    }

    public static function subtract(string $minuend, string $subtrahend): string
    {
        return self::fromThousandths(self::toThousandths($minuend) - self::toThousandths($subtrahend));
    }

    public static function compare(string $left, string $right): int
    {
        return self::toThousandths($left) <=> self::toThousandths($right);
    }

    private static function toThousandths(string $value): int
    {
        $value = trim($value);

        if (1 !== preg_match('/^([+-]?)(?=\.?\d)(\d*)(?:\.(\d*))?$/', $value, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid decimal value "%s".', $value));
        }

        $sign = '-' === $matches[1] ? -1 : 1;
        $integer = '' === $matches[2] ? '0' : $matches[2];
        // Pad to four digits so the first discarded digit can be inspected.
        $fraction = str_pad($matches[3] ?? '', 4, '0');

        $thousandths = (int) $integer * self::SCALE + (int) substr($fraction, 0, 3);

        if ((int) $fraction[3] >= 5) {
            ++$thousandths;
        }

        return $sign * $thousandths;
    }

    private static function fromThousandths(int $thousandths): string
    {
        $sign = $thousandths < 0 ? '-' : '';
        $absolute = abs($thousandths);

        return sprintf('%s%d.%03d', $sign, intdiv($absolute, self::SCALE), $absolute % self::SCALE);
    }
}
