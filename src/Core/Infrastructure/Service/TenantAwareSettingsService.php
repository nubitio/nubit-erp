<?php

namespace App\Core\Infrastructure\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class TenantAwareSettingsService
{
    public function __construct(
        private Connection $db,
        private CacheInterface $cache,
        private RequestStack $request
    ) {
    }

    /**
     * @return bool|float|int|string|null
     *
     * @throws InvalidArgumentException
     */
    public function get(string $key, string $default = null): mixed
    {
        $tenant = $this->request->getCurrentRequest()?->headers->get('tenant') ?? 'default';
        $tenantKey = $tenant.'_'.$key;
        /** @var bool|float|int|string|null $value */
        $value = $this->cache->get($tenantKey, function (ItemInterface $item) use ($key, $default) {
            $item->expiresAfter(10);
            $value = $this->db->fetchOne('SELECT value FROM options WHERE name = ?', [$key]);

            if (false === $value) {
                return $default;
            }

            return $value;
        });

        return $value;
    }

    /**
     * @throws Exception
     */
    public function set(string $key, mixed $value): void
    {
        $this->db->executeStatement('INSERT INTO options (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?', [$key, $value, $value]);
    }
}
