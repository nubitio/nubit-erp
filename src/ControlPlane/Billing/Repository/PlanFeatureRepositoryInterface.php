<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Repository;

use App\ControlPlane\Billing\Entity\PlanFeature;

interface PlanFeatureRepositoryInterface
{
    /**
     * Returns all features configured for the given plan.
     *
     * @return list<PlanFeature>
     */
    public function findByPlan(string $planName): array;

    /**
     * Returns a single feature entry for the given plan + key combination, or null.
     */
    public function findOneByPlanAndKey(string $planName, string $featureKey): ?PlanFeature;

    /**
     * Creates or updates a plan_features row for the given plan + feature key.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE so it is safe to call for both
     * new and existing rows (the UNIQUE KEY uq_plan_feature handles dedup).
     *
     * @param array<string, mixed>|null $config
     */
    public function upsert(string $planName, string $featureKey, bool $isEnabled, ?array $config): void;
}
