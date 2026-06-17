<?php

declare(strict_types=1);

namespace App\Tests\Settings\Vertical;

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

    public function testUnknownPresetThrows(): void
    {
        $registry = new VerticalPresetRegistry([new SingleStoreRetailPreset()]);

        self::assertFalse($registry->has('does_not_exist'));
        $this->expectException(\InvalidArgumentException::class);
        $registry->get('does_not_exist');
    }
}
