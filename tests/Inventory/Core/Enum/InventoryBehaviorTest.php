<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Enum;

use App\Inventory\Core\Enum\InventoryBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InventoryBehavior enum.
 *
 * Covers tracksInventory(), generatesOwnMovements(), requiresBomExplosion(),
 * and the legacy fromProductType() factory.
 */
final class InventoryBehaviorTest extends TestCase
{
    // ─── tracksInventory() ───────────────────────────────────────────────────────

    public function testTrackedTracksInventory(): void
    {
        self::assertTrue(InventoryBehavior::TRACKED->tracksInventory());
    }

    public function testCompositeTracksInventory(): void
    {
        self::assertTrue(InventoryBehavior::COMPOSITE->tracksInventory());
    }

    public function testNotTrackedDoesNotTrackInventory(): void
    {
        self::assertFalse(InventoryBehavior::NOT_TRACKED->tracksInventory());
    }

    // ─── generatesOwnMovements() ─────────────────────────────────────────────────

    public function testTrackedGeneratesOwnMovements(): void
    {
        self::assertTrue(InventoryBehavior::TRACKED->generatesOwnMovements());
    }

    public function testCompositeDoesNotGenerateOwnMovements(): void
    {
        self::assertFalse(InventoryBehavior::COMPOSITE->generatesOwnMovements());
    }

    public function testNotTrackedDoesNotGenerateOwnMovements(): void
    {
        self::assertFalse(InventoryBehavior::NOT_TRACKED->generatesOwnMovements());
    }

    // ─── requiresBomExplosion() ──────────────────────────────────────────────────

    public function testCompositeRequiresBomExplosion(): void
    {
        self::assertTrue(InventoryBehavior::COMPOSITE->requiresBomExplosion());
    }

    public function testTrackedDoesNotRequireBomExplosion(): void
    {
        self::assertFalse(InventoryBehavior::TRACKED->requiresBomExplosion());
    }

    public function testNotTrackedDoesNotRequireBomExplosion(): void
    {
        self::assertFalse(InventoryBehavior::NOT_TRACKED->requiresBomExplosion());
    }

    // ─── fromProductType() ───────────────────────────────────────────────────────

    /**
     * @dataProvider productTypeProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('productTypeProvider')]
    public function testFromProductType(string $input, InventoryBehavior $expected): void
    {
        self::assertSame($expected, InventoryBehavior::fromProductType($input));
    }

    /** @return array<string, array{string, InventoryBehavior}> */
    public static function productTypeProvider(): array
    {
        return [
            'COMPOSITE uppercase'  => ['COMPOSITE', InventoryBehavior::COMPOSITE],
            'composite lowercase'  => ['composite', InventoryBehavior::COMPOSITE],
            'SERVICE uppercase'    => ['SERVICE', InventoryBehavior::NOT_TRACKED],
            'service lowercase'    => ['service', InventoryBehavior::NOT_TRACKED],
            'DIGITAL uppercase'    => ['DIGITAL', InventoryBehavior::NOT_TRACKED],
            'digital lowercase'    => ['digital', InventoryBehavior::NOT_TRACKED],
            'GOOD → TRACKED'       => ['GOOD', InventoryBehavior::TRACKED],
            'PRODUCT → TRACKED'    => ['PRODUCT', InventoryBehavior::TRACKED],
            'unknown → TRACKED'    => ['UNKNOWN_TYPE', InventoryBehavior::TRACKED],
            'empty string → TRACKED' => ['', InventoryBehavior::TRACKED],
        ];
    }

    // ─── Invariant: exactly one case does NOT track inventory ────────────────────

    public function testOnlyNotTrackedSkipsInventory(): void
    {
        $nonTracking = array_filter(
            InventoryBehavior::cases(),
            static fn (InventoryBehavior $b) => !$b->tracksInventory(),
        );

        self::assertCount(1, $nonTracking);
        self::assertSame(InventoryBehavior::NOT_TRACKED, array_values($nonTracking)[0]);
    }

    // ─── Invariant: exactly one case generates own movements ────────────────────

    public function testOnlyTrackedGeneratesOwnMovements(): void
    {
        $generating = array_filter(
            InventoryBehavior::cases(),
            static fn (InventoryBehavior $b) => $b->generatesOwnMovements(),
        );

        self::assertCount(1, $generating);
        self::assertSame(InventoryBehavior::TRACKED, array_values($generating)[0]);
    }
}
