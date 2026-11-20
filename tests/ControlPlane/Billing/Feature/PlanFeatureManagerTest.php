<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing\Feature;

use App\ControlPlane\Billing\Feature\FeatureCatalog;
use App\ControlPlane\Billing\Feature\PlanFeatureManager;
use App\Tests\ControlPlane\Billing\Feature\Fixtures\RecordingPlanFeatureRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class PlanFeatureManagerTest extends TestCase
{
    public function test_setFeature_rejects_a_key_outside_the_catalogue(): void
    {
        $manager = new PlanFeatureManager(new RecordingPlanFeatureRepository(), $this->noopCache());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown feature key "nope"/');

        $manager->setFeature('basic', 'nope', true, null);
    }

    public function test_seedFeaturesForNewPlan_uses_catalogue_defaults_for_that_plan(): void
    {
        $repository = new RecordingPlanFeatureRepository();
        new PlanFeatureManager($repository, $this->noopCache())->seedFeaturesForNewPlan('basic');

        self::assertCount(count(FeatureCatalog::keys()), $repository->calls, 'one upsert per catalogue key');

        $byKey = [];
        foreach ($repository->calls as $call) {
            self::assertSame('basic', $call['plan']);
            $byKey[$call['key']] = ['enabled' => $call['enabled'], 'config' => $call['config']];
        }

        self::assertSame(['enabled' => true, 'config' => null], $byKey['payroll']); // module on basic
        self::assertSame(['enabled' => false, 'config' => null], $byKey['multi_warehouse']); // add-on not on basic
        self::assertTrue($byKey['ai_assistant']['enabled']);
        self::assertSame(['monthlyTokenCap' => 200_000, 'modelTier' => 'chat'], $byKey['ai_assistant']['config']);
    }

    public function test_seedFeaturesForNewPlan_defaults_everything_off_on_an_unknown_plan(): void
    {
        $repository = new RecordingPlanFeatureRepository();
        new PlanFeatureManager($repository, $this->noopCache())->seedFeaturesForNewPlan('enterprise_custom');

        foreach ($repository->calls as $call) {
            self::assertFalse($call['enabled'], sprintf(
                '"%s" should default disabled on an unknown plan',
                $call['key'],
            ));
            self::assertNull($call['config']);
        }
    }

    private function noopCache(): TagAwareCacheInterface
    {
        return new class implements TagAwareCacheInterface {
            /**
             * @param array<array-key, mixed>|null $metadata
             */
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                throw new \LogicException('This test does not read from the cache.');
            }

            public function delete(string $key): bool
            {
                return true;
            }

            /**
             * @param string[] $tags
             */
            public function invalidateTags(array $tags): bool
            {
                return true;
            }
        };
    }
}
