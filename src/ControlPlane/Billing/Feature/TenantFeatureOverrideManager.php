<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Feature;

use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Encapsulates CRUD operations for tenant feature overrides and ensures
 * the per-tenant feature cache is invalidated on every mutation.
 *
 * AC-14: Cache is invalidated whenever an override is created, updated, or removed.
 */
final class TenantFeatureOverrideManager
{
    private readonly Connection $cp;

    public function __construct(
        private readonly TenantFeatureOverrideRepositoryInterface $repository,
        private readonly TagAwareCacheInterface $featureCache,
        ConnectionRegistry $connectionRegistry,
    ) {
        /** @var Connection $cp */
        $cp = $connectionRegistry->getConnection('control_plane');
        $this->cp = $cp;
    }

    /**
     * Creates or updates a feature override for the given tenant + feature key.
     *
     * @param array<string, mixed>|null $config
     */
    public function setOverride(
        int $tenantId,
        string $featureKey,
        bool $isEnabled,
        ?array $config,
        ?\DateTimeImmutable $expiresAt,
        ?string $reason,
    ): void {
        $now = new \DateTimeImmutable()->format('Y-m-d H:i:s');
        $configJson = $config !== null ? json_encode($config, JSON_THROW_ON_ERROR) : null;
        $expiresAtStr = $expiresAt?->format('Y-m-d H:i:s');

        $existing = $this->repository->findByTenantAndKey($tenantId, $featureKey);

        if ($existing !== null) {
            $this->cp->update(
                'tenant_feature_overrides',
                [
                    'is_enabled' => $isEnabled ? 1 : 0,
                    'config_json' => $configJson,
                    'expires_at' => $expiresAtStr,
                    'reason' => $reason,
                    'cleaned_at' => null,
                    'updated_at' => $now,
                ],
                ['id' => $existing->id],
            );
        } else {
            $this->cp->insert('tenant_feature_overrides', [
                'tenant_id' => $tenantId,
                'feature_key' => $featureKey,
                'is_enabled' => $isEnabled ? 1 : 0,
                'config_json' => $configJson,
                'expires_at' => $expiresAtStr,
                'reason' => $reason,
                'cleaned_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // AC-14: Invalidate the feature cache for this tenant.
        $this->featureCache->invalidateTags(['tenant_features_' . $tenantId]);
    }

    /**
     * Removes the feature override for the given tenant + feature key.
     * No-op if the override does not exist.
     */
    public function removeOverride(int $tenantId, string $featureKey): void
    {
        $this->cp->delete('tenant_feature_overrides', [
            'tenant_id' => $tenantId,
            'feature_key' => $featureKey,
        ]);

        // AC-14: Invalidate the feature cache for this tenant.
        $this->featureCache->invalidateTags(['tenant_features_' . $tenantId]);
    }
}
