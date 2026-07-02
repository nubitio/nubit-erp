<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Repository;

use App\ControlPlane\Billing\Entity\TenantFeatureOverride;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;

/**
 * Reads and writes `tenant_feature_overrides` rows from the control-plane database.
 *
 * The control-plane connection has no dedicated ORM entity manager
 * (see config/packages/doctrine.yaml), so raw DBAL is used — consistent
 * with all other ControlPlane repositories in this project.
 */
final readonly class TenantFeatureOverrideRepository implements TenantFeatureOverrideRepositoryInterface
{
    private Connection $cp;

    public function __construct(ConnectionRegistry $connectionRegistry)
    {
        /** @var Connection $cp */
        $cp = $connectionRegistry->getConnection('control_plane');
        $this->cp = $cp;
    }

    /**
     * Returns all active (non-expired, non-cleaned) overrides for a tenant.
     *
     * "Active" means: `cleaned_at IS NULL` AND (`expires_at IS NULL` OR `expires_at > NOW()`).
     *
     * @return list<TenantFeatureOverride>
     */
    public function findActiveByTenant(int $tenantId): array
    {
        $rows = $this->cp->fetchAllAssociative(
            'SELECT * FROM tenant_feature_overrides
             WHERE tenant_id = ?
               AND cleaned_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY feature_key ASC',
            [$tenantId],
            [ParameterType::INTEGER],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * Returns the override for a specific tenant + feature key, or null if none exists.
     */
    public function findByTenantAndKey(int $tenantId, string $featureKey): ?TenantFeatureOverride
    {
        $row = $this->cp->fetchAssociative(
            'SELECT * FROM tenant_feature_overrides
             WHERE tenant_id = ? AND feature_key = ?
             LIMIT 1',
            [$tenantId, $featureKey],
            [ParameterType::INTEGER, ParameterType::STRING],
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Returns ALL overrides for a tenant (active, expired, and cleaned), ordered by feature_key.
     * Used by the admin UI to show the full override history.
     *
     * @return list<TenantFeatureOverride>
     */
    public function findAllByTenant(int $tenantId): array
    {
        $rows = $this->cp->fetchAllAssociative(
            'SELECT * FROM tenant_feature_overrides
             WHERE tenant_id = ?
             ORDER BY feature_key ASC',
            [$tenantId],
            [ParameterType::INTEGER],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * Returns all overrides that have expired but have not yet been cleaned.
     * Intended for use by a scheduled cleanup command / cron job.
     *
     * @return list<TenantFeatureOverride>
     */
    public function findExpiredUncleaned(): array
    {
        $rows = $this->cp->fetchAllAssociative('SELECT * FROM tenant_feature_overrides
             WHERE expires_at IS NOT NULL
               AND expires_at <= NOW()
               AND cleaned_at IS NULL
             ORDER BY expires_at ASC');

        return array_map($this->hydrate(...), $rows);
    }

    // -------------------------------------------------------------------------
    // Hydration
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): TenantFeatureOverride
    {
        /** @var array<string, mixed>|null $configJson */
        $configJson = isset($row['config_json'])
            ? json_decode((string) $row['config_json'], true, 512, JSON_THROW_ON_ERROR)
            : null;

        return new TenantFeatureOverride(
            id: (int) $row['id'],
            tenantId: (int) $row['tenant_id'],
            featureKey: (string) $row['feature_key'],
            enabled: (bool) $row['is_enabled'],
            configJson: $configJson,
            expiresAt: isset($row['expires_at']) ? new \DateTimeImmutable((string) $row['expires_at']) : null,
            reason: isset($row['reason']) ? (string) $row['reason'] : null,
            cleanedAt: isset($row['cleaned_at']) ? new \DateTimeImmutable((string) $row['cleaned_at']) : null,
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
        );
    }
}
