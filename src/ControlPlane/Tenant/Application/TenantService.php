<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Application;

use App\ControlPlane\Tenant\Domain\Model\TenantSnapshot;
use App\ControlPlane\Tenant\Domain\Repository\TenantDomainRepositoryInterface;
use App\ControlPlane\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\ControlPlane\Tenant\Infrastructure\Cache\TenantCacheInvalidator;
use App\ControlPlane\Tenant\Infrastructure\Persistence\TenantSourceMode;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Control-plane tenant use-case service.
 *
 * Replaces the legacy TenantManager god-class.
 */
readonly class TenantService
{
    private const int CACHE_TTL = 300;

    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantDomainRepositoryInterface $domainRepository,
        private TenantCacheInvalidator $cacheInvalidator,
        private CacheInterface $cache,
        #[Autowire('%env(default::TENANTS_SOURCE)%')]
        private string $tenantsSource,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTenants(): array
    {
        return $this->mapSnapshots($this->tenantRepository->listActive());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDeletedTenants(): array
    {
        if ($this->sourceMode() === TenantSourceMode::Yaml) {
            return [];
        }

        return $this->mapSnapshots($this->tenantRepository->listDeleted());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTenant(int $id): ?array
    {
        $tenant = $this->tenantRepository->findById($id);

        return $tenant?->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTenantByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $cacheKey = 'tenant_name_' . hash('xxh3', $name);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($name): ?array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->tenantRepository->findByName($name)?->toArray();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTenantByDomain(string $domain): ?array
    {
        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }

        $cacheKey = 'tenant_domain_' . hash('xxh3', $domain);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($domain): ?array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->tenantRepository->findByDomain($domain)?->toArray();
        });
    }

    /**
     * @param array<string, mixed> $tenant
     */
    public function addTenant(array $tenant): void
    {
        $name = trim((string) ($tenant['name'] ?? ''));
        $connection = trim((string) ($tenant['connection'] ?? ''));

        if ($name === '' || $connection === '') {
            throw new InvalidArgumentException('Tenant name and connection are required');
        }

        $this->tenantRepository->create($name, $connection);
        $this->cacheInvalidator->invalidateTenantName($name);
    }

    /**
     * @param array<string, mixed> $tenant
     */
    public function updateTenant(int $id, array $tenant): void
    {
        $name = trim((string) ($tenant['name'] ?? ''));
        $connection = trim((string) ($tenant['connection'] ?? ''));

        if ($name === '' || $connection === '') {
            throw new InvalidArgumentException('Tenant name and connection are required');
        }

        $oldTenant = $this->tenantRepository->findById($id);
        $this->tenantRepository->update($id, $name, $connection);

        if ($oldTenant !== null) {
            $this->cacheInvalidator->invalidateTenantName($oldTenant->name);
        }

        $this->cacheInvalidator->invalidateTenantName($name);
        $this->cacheInvalidator->invalidateTenantDomains($id);
    }

    public function deleteTenant(int $id): void
    {
        $tenant = $this->tenantRepository->findById($id);
        $this->tenantRepository->softDelete($id);

        if ($tenant !== null) {
            $this->cacheInvalidator->invalidateTenantName($tenant->name);
            $this->cacheInvalidator->invalidateTenantDomains($id);
        }
    }

    public function restoreTenant(int $id): void
    {
        $this->tenantRepository->restore($id);
        $this->cacheInvalidator->invalidateTenantDomains($id);
    }

    public function purgeTenant(int $id): void
    {
        $this->tenantRepository->purge($id);
    }

    public function suspendTenant(int $id): void
    {
        $this->tenantRepository->suspend($id);
        $this->invalidateAfterStatusChange($id);
    }

    public function unsuspendTenant(int $id): void
    {
        $this->tenantRepository->unsuspend($id);
        $this->invalidateAfterStatusChange($id);
    }

    public function changePlan(int $id, string $plan): void
    {
        $plan = strtolower(trim($plan));
        if ($plan === '') {
            throw new InvalidArgumentException('Plan is required');
        }

        $this->tenantRepository->changePlan($id, $plan);
        $this->invalidateAfterStatusChange($id);
        $this->cacheInvalidator->invalidateFeatureEntitlements($id);
    }

    public function updateBackupFrequency(int $id, int $frequency): void
    {
        $this->tenantRepository->updateBackupFrequency($id, $frequency);
        $this->invalidateTenantById($id);
    }

    public function updateBackupRetention(int $id, int $days): void
    {
        $this->tenantRepository->updateBackupRetention($id, $days);
        $this->invalidateTenantById($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTenantDomains(int $tenantId): array
    {
        return array_map(
            static fn(array $domain): array => [
                'id' => $domain['id'],
                'domain' => $domain['domain'],
                'is_primary' => $domain['is_primary'] ? 1 : 0,
            ],
            $this->domainRepository->listByTenantId($tenantId),
        );
    }

    public function getPrimaryDomain(int $tenantId): ?string
    {
        return $this->domainRepository->findPrimaryDomain($tenantId);
    }

    public function addTenantDomain(int $tenantId, string $domain, bool $isPrimary = false): void
    {
        $this->domainRepository->add($tenantId, $domain, $isPrimary);
        $this->cacheInvalidator->invalidateDomain(strtolower(trim($domain)));
    }

    public function deleteTenantDomain(int $domainId): void
    {
        $this->domainRepository->delete($domainId);
    }

    public function setPrimaryTenantDomain(int $tenantId, int $domainId): void
    {
        $this->domainRepository->setPrimary($tenantId, $domainId);
        $this->cacheInvalidator->invalidateTenantDomains($tenantId);
    }

    private function invalidateAfterStatusChange(int $id): void
    {
        $tenant = $this->tenantRepository->findById($id);
        if ($tenant !== null) {
            $this->cacheInvalidator->invalidateTenantName($tenant->name);
            $this->cacheInvalidator->invalidateTenantDomains($id);
        }
    }

    private function invalidateTenantById(int $id): void
    {
        $tenant = $this->tenantRepository->findById($id);
        if ($tenant !== null) {
            $this->cacheInvalidator->invalidateTenantName($tenant->name);
        }
    }

    /**
     * @param list<TenantSnapshot> $snapshots
     * @return array<int, array<string, mixed>>
     */
    private function mapSnapshots(array $snapshots): array
    {
        return array_map(
            static fn(TenantSnapshot $snapshot): array => $snapshot->toArray(),
            $snapshots,
        );
    }

    private function sourceMode(): TenantSourceMode
    {
        return TenantSourceMode::fromEnv($this->tenantsSource);
    }
}