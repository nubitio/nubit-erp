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
 *   - feature_key must be in {@see FeatureCatalog} (fail-fast with clear message)
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
     * Seeds every catalogue feature for a newly created plan, using the
     * catalogue's per-plan defaults. Uses upsert so it is idempotent.
     * No cache invalidation needed — a brand-new plan has no tenants yet.
     */
    public function seedFeaturesForNewPlan(string $planName): void
    {
        foreach (FeatureCatalog::keys() as $featureKey) {
            $default = FeatureCatalog::planDefault($featureKey, $planName);
            $this->repository->upsert($planName, $featureKey, $default['enabled'], $default['config']);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertFeatureKey(string $featureKey): void
    {
        if (!FeatureCatalog::has($featureKey)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown feature key "%s". Allowed keys: %s.',
                $featureKey,
                implode(', ', FeatureCatalog::keys()),
            ));
        }
    }
}
