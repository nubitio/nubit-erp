<?php

namespace App\Core\Infrastructure\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class SettingsService
{
    public function __construct(
        private Connection $db,
        private CacheInterface $cache
    ) {
    }

    public function get(string $key, ?string $default = null): mixed
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($key, $default) {
            $item->expiresAfter(10);
            $value = $this->db->fetchOne('SELECT value FROM options WHERE name = ?', [$key]);

            if (false === $value) {
                return $default;
            }

            return $value;
        });
    }

    /**
     * @throws Exception
     */
    public function set(string $key, mixed $value): void
    {
        $this->db->executeStatement('INSERT INTO options (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?', [$key, $value, $value]);
    }
}
