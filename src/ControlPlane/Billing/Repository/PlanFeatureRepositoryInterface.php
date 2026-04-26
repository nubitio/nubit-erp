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
}
