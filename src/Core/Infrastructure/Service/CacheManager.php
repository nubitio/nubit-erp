<?php

namespace App\Core\Infrastructure\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

readonly class CacheManager
{
    public function __construct(
        private CacheItemPoolInterface $cache
    )
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string $key): mixed
    {
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $item = $this->cache->getItem($key);
        $item->set($value);

        if ($ttl !== null) {
            $item->expiresAfter($ttl);
        }

        $this->cache->save($item);
    }
}