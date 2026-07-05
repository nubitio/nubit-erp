<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Infrastructure\Cache;

use App\ControlPlane\Tenant\Domain\Repository\TenantDomainRepositoryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class TenantCacheInvalidator
{
    public function __construct(
        private CacheInterface $cache,
        private TagAwareCacheInterface $featureCache,
        private TenantDomainRepositoryInterface $domainRepository,
    ) {}

    public function invalidateTenantName(string $tenantName): void
    {
        if ($tenantName === '') {
            return;
        }

        $this->cache->delete('tenant_name_' . hash('xxh3', $tenantName));
    }

    public function invalidateTenantDomains(int $tenantId): void
    {
        try {
            foreach ($this->domainRepository->listByTenantId($tenantId) as $domain) {
                $this->cache->delete('tenant_domain_' . hash('xxh3', $domain['domain']));
            }
        } catch (\Throwable) {
            // Best-effort invalidation
        }
    }

    public function invalidateDomain(string $domain): void
    {
        if ($domain === '') {
            return;
        }

        $this->cache->delete('tenant_domain_' . hash('xxh3', $domain));
    }

    public function invalidateFeatureEntitlements(int $tenantId): void
    {
        $this->featureCache->invalidateTags(['tenant_features_' . $tenantId]);
    }
}
