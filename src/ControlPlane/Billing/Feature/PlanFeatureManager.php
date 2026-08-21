<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Feature;

use App\ControlPlane\Billing\Repository\PlanFeatureRepositoryInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Encapsulates write operations for plan_features and ensures the plan-level
 * feature cache tag is invalidated on every mutation.
 *
 * Validation rules:
 *   - feature_key must be in FEATURE_KEYS catalogue (fail-fast with clear message)
 *   - config is passed already-parsed (?array); JSON encoding is the repository's job
 *   - plan existence is enforced by the FK constraint in plan_features; the controller
 *     validates against PlanManager before calling this service
 *
 * Cache invalidation:
 *   - Invalidates tag 'plan_features_{planName}', which is attached to every
 *     TenantFeatureChecker cache item that resolved its value from the plan
 *     (not from an override). A single invalidation clears all tenants on the plan.
 */
final class PlanFeatureManager
{
    /** Canonical catalogue of all feature keys. Single source of truth for the platform. */
    public const array FEATURE_KEYS = [
        'excel_export',
        'pdf_bulk_download',
        'api_access',
        'multi_warehouse',
        'lot_tracking',
        'custom_branding',
        'advanced_reports',
        'backup_download',
        'sunat_auto_retry',
        'webhooks',
        'priority_support',
        'ai_assistant',
        'api_rate_limit',
        'retention_days',
        'concurrent_sessions',
    ];

    /** Feature keys that carry a config_json payload (not purely boolean). */
    public const array CONFIG_FEATURE_KEYS = [
        'api_rate_limit',
        'retention_days',
        'concurrent_sessions',
        'ai_assistant',
    ];

    public function __construct(
        private readonly PlanFeatureRepositoryInterface $repository,
        private readonly TagAwareCacheInterface $featureCache,
    ) {}

    /**
     * Creates or updates the plan_features row for (planName, featureKey).
     *
     * @param array<string, mixed>|null $config  Already-decoded config array, or null.
     *
     * @throws \InvalidArgumentException if the feature key is not in the catalogue.
     */
    public function setFeature(string $planName, string $featureKey, bool $isEnabled, ?array $config): void
    {
        $this->assertFeatureKey($featureKey);

        $this->repository->upsert($planName, $featureKey, $isEnabled, $config);

        // Invalidate the plan-level cache tag so all tenants on this plan see
        // the updated value within the next request (no TTL wait needed).
        $this->featureCache->invalidateTags(['plan_features_' . $planName]);
    }

    /**
     * Seeds all catalogue feature keys for a newly created plan, all disabled by default.
     * Uses upsert so it is idempotent (safe to call multiple times).
     * No cache invalidation needed — a brand-new plan has no tenants yet.
     */
    public function seedFeaturesForNewPlan(string $planName): void
    {
        foreach (self::FEATURE_KEYS as $featureKey) {
            $this->repository->upsert($planName, $featureKey, false, null);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertFeatureKey(string $featureKey): void
    {
        if (!in_array($featureKey, self::FEATURE_KEYS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown feature key "%s". Allowed keys: %s.',
                $featureKey,
                implode(', ', self::FEATURE_KEYS),
            ));
        }
    }
}
