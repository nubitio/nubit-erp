<?php

declare(strict_types=1);

namespace App\Efact\RateLimit;

use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TenantRateLimiter
{
    private int $limitPerWindow;
    private int $windowSeconds;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private FeatureCheckerInterface $featureChecker,
        #[Autowire('%env(default:tenant_rate_limit:TENANT_RATE_LIMIT)%')]
        string $limit,
        #[Autowire('%env(default:tenant_rate_window:TENANT_RATE_WINDOW)%')]
        string $window,
    ) {
        $this->limitPerWindow = (int) $limit;
        $this->windowSeconds = (int) $window;
    }

    public function check(string $tenantName): RateLimitResult
    {
        $limit = $this->resolveLimit();

        if ($limit <= 0) {
            return new RateLimitResult(allowed: true, limit: 0, remaining: 0, retryAfter: 0);
        }

        $windowKey = (string) (int) (time() / $this->windowSeconds);
        $cacheKey = 'tenant_rl.' . preg_replace('/[^a-zA-Z0-9_.]/', '_', $tenantName) . '.' . $windowKey;

        $item = $this->cache->getItem($cacheKey);

        /** @var int $current */
        $current = $item->isHit() ? (int) $item->get() + 1 : 1;

        $item->set($current);
        $item->expiresAfter($this->windowSeconds);
        $this->cache->save($item);

        $remaining = max(0, $limit - $current);
        $allowed = $current <= $limit;
        $retryAfter = $allowed ? 0 : $this->windowSeconds - (time() % $this->windowSeconds);

        return new RateLimitResult(allowed: $allowed, limit: $limit, remaining: $remaining, retryAfter: $retryAfter);
    }

    /**
     * Resolves the effective per-window request limit for the current tenant.
     *
     * Resolution order:
     *   1. If the 'api_rate_limit' feature is enabled and its config contains
     *      'requests_per_hour', convert that to requests-per-window and use it.
     *   2. Otherwise fall back to the global env-var default ($limitPerWindow).
     *
     * A return value of 0 means "no limit enforced" (allow all requests).
     */
    private function resolveLimit(): int
    {
        if ($this->featureChecker->hasFeature('api_rate_limit')) {
            $config = $this->featureChecker->getFeatureConfig('api_rate_limit');
            $requestsPerHour = isset($config['requests_per_hour']) ? (int) $config['requests_per_hour'] : 0;

            if ($requestsPerHour > 0 && $this->windowSeconds > 0) {
                // Convert from requests/hour to requests/window
                return (int) ceil(($requestsPerHour * $this->windowSeconds) / 3600);
            }
        }

        return $this->limitPerWindow;
    }
}
