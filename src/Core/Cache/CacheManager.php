<?php

declare(strict_types=1);

namespace App\Core\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

readonly class CacheManager
{
    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
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

        if (null !== $ttl) {
            $item->expiresAfter($ttl);
        }

        $this->cache->save($item);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function has(string $key): bool
    {
        return $this->cache->hasItem($key);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function delete(string $key): void
    {
        $this->cache->deleteItem($key);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forget(string $key): void
    {
        $this->delete($key);
    }
}
