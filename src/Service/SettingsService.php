<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SettingsService
{
    private Connection $db;
    private CacheInterface $cache;

    public function __construct(Connection $db, CacheInterface $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function get(string $key, $default = null)
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($key, $default) {
            $item->expiresAfter(3600);
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
    public function set(string $key, $value): void
    {
        $this->db->executeStatement('INSERT INTO options (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?', [$key, $value, $value]);
    }
}
