<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Repository;

use App\ControlPlane\Billing\Entity\TenantFeatureOverride;

interface TenantFeatureOverrideRepositoryInterface
{
    /**
     * Returns all active (non-expired, non-cleaned) overrides for a tenant.
     *
     * @return list<TenantFeatureOverride>
     */
    public function findActiveByTenant(int $tenantId): array;

    /**
     * Returns the override for a specific tenant + feature key, or null if none exists.
     */
    public function findByTenantAndKey(int $tenantId, string $featureKey): ?TenantFeatureOverride;

    /**
     * Returns ALL overrides for a tenant (active, expired, and cleaned), ordered by feature_key.
     * Used by the admin UI to show the full override history.
     *
     * @return list<TenantFeatureOverride>
     */
    public function findAllByTenant(int $tenantId): array;

    /**
     * Returns all overrides that have expired but have not yet been cleaned.
     * Intended for use by a scheduled cleanup command / cron job.
     *
     * @return list<TenantFeatureOverride>
     */
    public function findExpiredUncleaned(): array;
}
