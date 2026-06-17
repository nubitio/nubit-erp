<?php

declare(strict_types=1);

namespace App\Tests\Settings\Vertical;

use App\Settings\Vertical\Preset\PharmacyPreset;
use App\Settings\Vertical\Preset\SingleStoreRetailPreset;
use App\Settings\Vertical\VerticalPresetRegistry;
use PHPUnit\Framework\TestCase;

final class VerticalPresetRegistryTest extends TestCase
{
    public function testResolvesRegisteredPresetByKey(): void
    {
        $preset = new SingleStoreRetailPreset();
        $registry = new VerticalPresetRegistry([$preset]);

        self::assertTrue($registry->has(SingleStoreRetailPreset::KEY));
        self::assertSame($preset, $registry->get(SingleStoreRetailPreset::KEY));
        self::assertSame([SingleStoreRetailPreset::KEY], $registry->keys());
        self::assertSame([$preset], $registry->all());
    }

    public function testResolvesMultipleVerticalsIndependently(): void
    {
        $retail = new SingleStoreRetailPreset();
        $pharmacy = new PharmacyPreset();
        $registry = new VerticalPresetRegistry([$retail, $pharmacy]);

        self::assertTrue($registry->has(PharmacyPreset::KEY));
        self::assertSame($pharmacy, $registry->get(PharmacyPreset::KEY));
        self::assertContains('inventory.lot_tracking', $pharmacy->desiredCapabilities());
        self::assertNotContains('inventory.lot_tracking', $retail->desiredCapabilities());
        self::assertEqualsCanonicalizing(
            [SingleStoreRetailPreset::KEY, PharmacyPreset::KEY],
            $registry->keys(),
        );
    }

    public function testUnknownPresetThrows(): void
    {
        $registry = new VerticalPresetRegistry([new SingleStoreRetailPreset()]);

        self::assertFalse($registry->has('does_not_exist'));
        $this->expectException(\InvalidArgumentException::class);
        $registry->get('does_not_exist');
    }
}
