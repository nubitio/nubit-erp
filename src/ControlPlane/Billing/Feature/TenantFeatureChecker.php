<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Feature;

use App\ControlPlane\Billing\Repository\PlanFeatureRepositoryInterface;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use App\ControlPlane\Tenant\Application\TenantService;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Nubit\Platform\Feature\Exception\FeatureNotAvailableException;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Central feature-gate service for the eFact control plane.
 *
 * Resolution order (fail-closed at every step):
 *   1. No tenant in context                          → false
 *   2. Active tenant_feature_override for this key   → use its enabled/config
 *   3. plan_features row for the tenant's plan       → use its enabled/config
 *   4. Not found anywhere                            → false
 *
 * All results are cached per-tenant with a 300-second TTL and tagged so that
 * the entire tenant's feature cache can be invalidated atomically.
 */
final class TenantFeatureChecker implements FeatureCheckerInterface
{
    private const int CACHE_TTL = 300;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantService $tenantService,
        private readonly PlanFeatureRepositoryInterface $planFeatureRepository,
        private readonly TenantFeatureOverrideRepositoryInterface $overrideRepository,
        private readonly TagAwareCacheInterface $featureCache,
    ) {}

    // -------------------------------------------------------------------------
    // FeatureCheckerInterface
    // -------------------------------------------------------------------------

    public function hasFeature(string $featureKey): bool
    {
        $tenantId = $this->tenantContext->getTenantId();
        if ($tenantId === null) {
            return false;
        }

        $cacheKey = sprintf('feature.%d.%s', $tenantId, $this->sanitizeCacheKey($featureKey));
        $tag = sprintf('tenant_features_%d', $tenantId);

        /** @var array{enabled: bool, config: array<string, mixed>} $resolved */
        $resolved = $this->featureCache->get($cacheKey, function (ItemInterface $item) use ($featureKey, $tag): array {
            $item->tag([$tag]);
            $item->expiresAfter(self::CACHE_TTL);

            return $this->resolveFeature($featureKey, $item);
        });

        return $resolved['enabled'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeatureConfig(string $featureKey): array
    {
        $tenantId = $this->tenantContext->getTenantId();
        if ($tenantId === null) {
            return [];
        }

        $cacheKey = sprintf('feature_config.%d.%s', $tenantId, $this->sanitizeCacheKey($featureKey));
        $tag = sprintf('tenant_features_%d', $tenantId);

        /** @var array{enabled: bool, config: array<string, mixed>} $resolved */
        $resolved = $this->featureCache->get($cacheKey, function (ItemInterface $item) use ($featureKey, $tag): array {
            $item->tag([$tag]);
            $item->expiresAfter(self::CACHE_TTL);

            return $this->resolveFeature($featureKey, $item);
        });

        if (!$resolved['enabled']) {
            return [];
        }

        return $resolved['config'];
    }

    public function requireFeature(string $featureKey): void
    {
        if (!$this->hasFeature($featureKey)) {
            throw new FeatureNotAvailableException($featureKey);
        }
    }

    /**
     * @return array<string, array{enabled: bool, config: array<string, mixed>}>
     */
    public function getEntitlements(): array
    {
        $tenantId = $this->tenantContext->getTenantId();
        if ($tenantId === null) {
            return [];
        }

        $cacheKey = sprintf('feature_entitlements.%d', $tenantId);
        $tag = sprintf('tenant_features_%d', $tenantId);

        /** @var array<string, array{enabled: bool, config: array<string, mixed>}> $entitlements */
        $entitlements = $this->featureCache->get($cacheKey, function (ItemInterface $item) use (
            $tenantId,
            $tag,
        ): array {
            $item->tag([$tag]);
            $item->expiresAfter(self::CACHE_TTL);

            return $this->buildEntitlements($tenantId, $item);
        });

        return $entitlements;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Core resolution logic — no cache, no side effects.
     *
     * Receives the cache ItemInterface so it can attach the plan-level tag
     * ('plan_features_{planName}') when the value is resolved from the plan
     * (not from an override). This enables O(1) plan-wide cache invalidation
     * via PlanFeatureManager::setFeature().
     *
     * The tag is NOT added when an active override wins, because in that case
     * the cached value is independent of the plan and should not be evicted
     * when the plan feature changes.
     *
     * @return array{enabled: bool, config: array<string, mixed>}
     */
    private function resolveFeature(string $featureKey, ItemInterface $item): array
    {
        $tenantId = $this->tenantContext->getTenantId();
        if ($tenantId === null) {
            return ['enabled' => false, 'config' => []];
        }

        // Step 1: check for a per-tenant override (any row, active or expired).
        $override = $this->overrideRepository->findByTenantAndKey($tenantId, $featureKey);
        if ($override !== null) {
            if ($override->isActive()) {
                // Active override wins — use its enabled flag and config.
                // No plan tag: this value is override-sourced, not plan-sourced.
                return [
                    'enabled' => $override->isEnabled(),
                    'config' => $override->getConfigJson() ?? [],
                ];
            }

            // Expired override → fall through to plan lookup.
        }

        // Step 2: resolve the tenant's plan and look up the plan feature.
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return ['enabled' => false, 'config' => []];
        }

        $tenant = $this->tenantService->getTenantByName($tenantName);
        if ($tenant === null) {
            // Tenant context set but no control-plane record → fail closed.
            return ['enabled' => false, 'config' => []];
        }

        $planName = (string) ($tenant['plan'] ?? '');
        if ($planName === '') {
            return ['enabled' => false, 'config' => []];
        }

        // Tag this cache item with the plan tag so that PlanFeatureManager can
        // invalidate all tenants on this plan with a single invalidateTags() call.
        $item->tag(['plan_features_' . $planName]);

        $planFeature = $this->planFeatureRepository->findOneByPlanAndKey($planName, $featureKey);
        if ($planFeature === null) {
            // Feature not defined for this plan → fail closed.
            return ['enabled' => false, 'config' => []];
        }

        return [
            'enabled' => $planFeature->isEnabled(),
            'config' => $planFeature->getConfigJson() ?? [],
        ];
    }

    /**
     * Builds the full entitlements map for a tenant.
     * Merges plan features with active overrides (overrides take precedence).
     *
     * Attaches the plan-level cache tag ('plan_features_{planName}') to the
     * cache item so that a plan feature change invalidates this entry.
     *
     * @return array<string, array{enabled: bool, config: array<string, mixed>}>
     */
    private function buildEntitlements(int $tenantId, ItemInterface $item): array
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return [];
        }

        $tenant = $this->tenantService->getTenantByName($tenantName);
        if ($tenant === null) {
            return [];
        }

        $planName = (string) ($tenant['plan'] ?? '');

        /** @var array<string, array{enabled: bool, config: array<string, mixed>}> $entitlements */
        $entitlements = [];

        // Seed from plan features.
        if ($planName !== '') {
            // Tag so PlanFeatureManager can invalidate all tenants on this plan at once.
            $item->tag(['plan_features_' . $planName]);

            foreach ($this->planFeatureRepository->findByPlan($planName) as $planFeature) {
                $entitlements[$planFeature->featureKey] = [
                    'enabled' => $planFeature->isEnabled(),
                    'config' => $planFeature->getConfigJson() ?? [],
                ];
            }
        }

        // Apply active overrides on top (override wins).
        foreach ($this->overrideRepository->findActiveByTenant($tenantId) as $override) {
            $entitlements[$override->featureKey] = [
                'enabled' => $override->isEnabled(),
                'config' => $override->getConfigJson() ?? [],
            ];
        }

        return $entitlements;
    }

    /**
     * Strips characters that are invalid in Symfony cache keys.
     * Symfony Cache forbids: { } ( ) / \ @ :
     */
    private function sanitizeCacheKey(string $key): string
    {
        return preg_replace('/[{}()\/\\\\@:]/', '_', $key) ?? $key;
    }
}
