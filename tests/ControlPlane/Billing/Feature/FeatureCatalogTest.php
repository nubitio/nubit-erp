<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing\Feature;

use App\ControlPlane\Billing\Feature\FeatureCatalog;
use PHPUnit\Framework\TestCase;

final class FeatureCatalogTest extends TestCase
{
    public function test_keys_are_non_empty_unique_and_well_formed(): void
    {
        $keys = FeatureCatalog::keys();

        self::assertNotEmpty($keys);
        self::assertSame($keys, array_values(array_unique($keys)), 'keys must be unique');

        foreach ($keys as $key) {
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key);
        }
    }

    public function test_config_keys_are_a_subset_of_keys_and_match_hasConfig(): void
    {
        $configKeys = FeatureCatalog::configKeys();

        self::assertSame($configKeys, array_values(array_intersect($configKeys, FeatureCatalog::keys())));

        foreach (FeatureCatalog::all() as $key => $feature) {
            self::assertSame(
                $feature['hasConfig'],
                in_array($key, $configKeys, true),
                sprintf('hasConfig for "%s" disagrees with configKeys()', $key),
            );
        }
    }

    public function test_every_feature_category_resolves(): void
    {
        $categories = FeatureCatalog::categories();

        foreach (FeatureCatalog::all() as $key => $feature) {
            self::assertArrayHasKey(
                $feature['category'],
                $categories,
                sprintf('feature "%s" has unknown category "%s"', $key, $feature['category']),
            );
        }
    }

    public function test_list_shape_matches_keys(): void
    {
        $list = FeatureCatalog::list();

        self::assertCount(count(FeatureCatalog::keys()), $list);
        self::assertSame(FeatureCatalog::keys(), array_column($list, 'key'));

        foreach ($list as $entry) {
            self::assertArrayHasKey('label', $entry);
            self::assertArrayHasKey('category', $entry);
            self::assertArrayHasKey('hasConfig', $entry);
        }
    }

    public function test_has(): void
    {
        self::assertTrue(FeatureCatalog::has('excel_export'));
        self::assertTrue(FeatureCatalog::has('payroll'));
        self::assertFalse(FeatureCatalog::has('does_not_exist'));
    }

    public function test_plan_default_falls_back_closed(): void
    {
        self::assertSame(['enabled' => false, 'config' => null], FeatureCatalog::planDefault('nope', 'free'));
        self::assertSame(
            ['enabled' => false, 'config' => null],
            FeatureCatalog::planDefault('payroll', 'no_such_plan'),
        );

        $basic = FeatureCatalog::planDefault('payroll', 'basic');
        self::assertTrue($basic['enabled']);
        self::assertNull($basic['config']);

        $ai = FeatureCatalog::planDefault('ai_assistant', 'pro');
        self::assertTrue($ai['enabled']);
        self::assertSame(['monthlyTokenCap' => 1_000_000, 'modelTier' => 'chat'], $ai['config']);
    }
}
