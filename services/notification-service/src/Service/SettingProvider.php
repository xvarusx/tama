<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SettingRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Reads settings from the database with a 60-second cache. Code defaults keep
 * the service usable before fixtures are loaded or when a key is removed.
 */
class SettingProvider
{
    private const string CACHE_KEY = 'notifications.settings';

    /** @var array<string, string> */
    private const array DEFAULTS = [
        'notifications.default_locale' => 'en',
        'notifications.retention_days' => '30',
        'notifications.max_attempts' => '3',
        'notifications.retry_base_seconds' => '30',
        'notifications.retry_max_seconds' => '300',
        'notifications.sending_timeout_seconds' => '600',
        'notifications.from_address' => 'no-reply@example.com',
        'notifications.from_name' => 'ERP Notifications',
    ];

    public function __construct(
        private readonly SettingRepository $settings,
        private readonly CacheInterface $cache,
    ) {}

    public function string(string $key): string
    {
        return $this->all()[$key] ?? self::DEFAULTS[$key] ?? '';
    }

    public function int(string $key): int
    {
        return (int)$this->string($key);
    }

    /** Call after an administrator updates a setting (SDD ruling R4). */
    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    /** @return array<string, string> */
    private function all(): array
    {
        /** @var array<string, string> $values */
        $values = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(60);

            $values = self::DEFAULTS;
            foreach ($this->settings->all() as $setting) {
                $values[$setting->getKey()] = $setting->getValue();
            }

            return $values;
        }, 0.0);

        return $values;
    }
}
