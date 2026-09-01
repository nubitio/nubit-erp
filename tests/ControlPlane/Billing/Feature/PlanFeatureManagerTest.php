<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing\Feature;

use App\ControlPlane\Billing\Entity\PlanFeature;
use App\ControlPlane\Billing\Feature\FeatureCatalog;
use App\ControlPlane\Billing\Feature\PlanFeatureManager;
use App\ControlPlane\Billing\Repository\PlanFeatureRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class PlanFeatureManagerTest extends TestCase
{
    public function test_setFeature_rejects_a_key_outside_the_catalogue(): void
    {
        $manager = new PlanFeatureManager($this->recordingRepository(), $this->noopCache());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown feature key "nope"/');

        $manager->setFeature('basic', 'nope', true, null);
    }

    public function test_seedFeaturesForNewPlan_uses_catalogue_defaults_for_that_plan(): void
    {
        $repository = $this->recordingRepository();
        (new PlanFeatureManager($repository, $this->noopCache()))
            ->seedFeaturesForNewPlan('basic');

        self::assertCount(count(FeatureCatalog::keys()), $repository->calls, 'one upsert per catalogue key');

        $byKey = [];
        foreach ($repository->calls as [$plan, $key, $enabled, $config]) {
            self::assertSame('basic', $plan);
            $byKey[$key] = ['enabled' => $enabled, 'config' => $config];
        }

        self::assertSame(['enabled' => true, 'config' => null], $byKey['payroll']);          // module on basic
        self::assertSame(['enabled' => false, 'config' => null], $byKey['multi_warehouse']); // add-on not on basic
        self::assertTrue($byKey['ai_assistant']['enabled']);
        self::assertSame(['monthlyTokenCap' => 200000, 'modelTier' => 'chat'], $byKey['ai_assistant']['config']);
    }

    public function test_seedFeaturesForNewPlan_defaults_everything_off_on_an_unknown_plan(): void
    {
        $repository = $this->recordingRepository();
        (new PlanFeatureManager($repository, $this->noopCache()))
            ->seedFeaturesForNewPlan('enterprise_custom');

        foreach ($repository->calls as [, $key, $enabled, $config]) {
            self::assertFalse($enabled, sprintf('"%s" should default disabled on an unknown plan', $key));
            self::assertNull($config);
        }
    }

    private function noopCache(): TagAwareCacheInterface
    {
        return new class implements TagAwareCacheInterface {
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                return null;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function invalidateTags(array $tags): bool
            {
                return true;
            }
        };
    }

    private function recordingRepository(): PlanFeatureRepositoryInterface
    {
        return new class implements PlanFeatureRepositoryInterface {
            /** @var list<array{0:string,1:string,2:bool,3:?array<string,mixed>}> */
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
                $this->calls[] = [$planName, $featureKey, $isEnabled, $config];
            }
        };
    }
}
