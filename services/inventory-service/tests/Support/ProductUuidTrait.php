<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Uid\Uuid;

/** Deterministic product UUIDs so tests can express id relationships numerically. */
trait ProductUuidTrait
{
    protected function productUuid(int $number): string
    {
        return Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), sprintf('inventory-product-%03d', $number))->toRfc4122();
    }
}
