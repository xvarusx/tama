<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findActiveByKey(string $key): ?Setting
    {
        return $this->find($key);
    }

    public function getValue(string $key, ?string $default = null): ?string
    {
        $setting = $this->find($key);
        return $setting?->getValue() ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $setting = $this->find($key);
        return $setting?->asInt() ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $setting = $this->find($key);
        return $setting?->asBool() ?? $default;
    }
}