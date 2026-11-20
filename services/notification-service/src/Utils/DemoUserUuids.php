<?php

declare(strict_types=1);

namespace App\Utils;

use Symfony\Component\Uid\Uuid;

/**
 * Deterministic UUIDs for the notification demo users.
 *
 * Fixtures and tests refer to the same logical users, so their ids must be
 * stable across runs instead of being regenerated with Uuid::v7().
 */
final class DemoUserUuids
{
    public const string NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public static function forNumber(int $number): string
    {
        return Uuid::v5(Uuid::fromString(self::NAMESPACE), 'erp-demo-user-' . $number)->toRfc4122();
    }
}
