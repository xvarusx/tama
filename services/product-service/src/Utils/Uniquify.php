<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Pick the first unused candidate from a list of already-taken slugs.
 *
 * "wireless-mice" is fine when nothing else uses it; otherwise try
 * "wireless-mice-2", "wireless-mice-3", ... until one is free.
 */
final class Uniquify
{
    /**
     * @param string[] $taken
     */
    public static function make(string $base, array $taken): string
    {
        $taken = array_flip($taken);
        if (!isset($taken[$base])) {
            return $base;
        }

        $i = 2;
        while (isset($taken[$base . '-' . $i])) {
            ++$i;
        }

        return $base . '-' . $i;
    }
}
