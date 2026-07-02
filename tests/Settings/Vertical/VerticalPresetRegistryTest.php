<?php

declare(strict_types=1);

namespace App\Tests\Settings\Vertical;

use App\Settings\Vertical\Preset\PharmacyPreset;
use App\Settings\Vertical\Preset\RetailPreset;
use App\Settings\Vertical\VerticalPresetRegistry;
use PHPUnit\Framework\TestCase;

final class VerticalPresetRegistryTest extends TestCase
{
    public function testResolvesRegisteredPresetByKey(): void
    {
        $preset = new RetailPreset();
        $registry = new VerticalPresetRegistry([$preset]);

        self::assertTrue($registry->has(RetailPreset::KEY));
        self::assertSame($preset, $registry->get(RetailPreset::KEY));
        self::assertSame([RetailPreset::KEY], $registry->keys());
        self::assertSame([$preset], $registry->all());
    }

    public function testResolvesMultipleVerticalsIndependently(): void
    {
        $retail = new RetailPreset();
        $pharmacy = new PharmacyPreset();
        $registry = new VerticalPresetRegistry([$retail, $pharmacy]);

        self::assertTrue($registry->has(PharmacyPreset::KEY));
        self::assertSame($pharmacy, $registry->get(PharmacyPreset::KEY));
        self::assertContains('inventory.lot_tracking', $pharmacy->desiredCapabilities());
        self::assertNotContains('inventory.lot_tracking', $retail->desiredCapabilities());
        self::assertEqualsCanonicalizing([RetailPreset::KEY, PharmacyPreset::KEY], $registry->keys());
    }

    public function testUnknownPresetThrows(): void
    {
        $registry = new VerticalPresetRegistry([new RetailPreset()]);

        self::assertFalse($registry->has('does_not_exist'));
        $this->expectException(\InvalidArgumentException::class);
        $registry->get('does_not_exist');
    }
}
