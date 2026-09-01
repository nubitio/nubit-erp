<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing\Feature\Fixtures;

use App\ControlPlane\Billing\Entity\PlanFeature;
use App\ControlPlane\Billing\Repository\PlanFeatureRepositoryInterface;

/**
 * In-memory {@see PlanFeatureRepositoryInterface} that records every
 * {@see upsert()} call so a test can assert on what was written. A named class
 * (rather than an anonymous one) so the static analyzer sees {@see $calls}.
 */
final class RecordingPlanFeatureRepository implements PlanFeatureRepositoryInterface
{
    /** @var list<array{plan: string, key: string, enabled: bool, config: array<string, mixed>|null}> */
    public array $calls = [];

    public function findByPlan(string $planName): array
    {
        return [];
    }

    public function findOneByPlanAndKey(string $planName, string $featureKey): ?PlanFeature
    {
        return null;
    }

    public function upsert(string $planName, string $featureKey, bool $isEnabled, ?array $config): void
    {
        $this->calls[] = [
            'plan' => $planName,
            'key' => $featureKey,
            'enabled' => $isEnabled,
            'config' => $config,
        ];
    }
}
