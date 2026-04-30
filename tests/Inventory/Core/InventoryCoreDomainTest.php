<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core;

use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Dto\StockAuthorizationResult;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\ProductKind;
use App\Inventory\Core\Enum\ProductType;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Event\StockMovementRecorded;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Domain DTOs, enums and events for the Inventory Core sub-context.
 */
final class InventoryCoreDomainTest extends TestCase
{
    // ── StockValidationResult ─────────────────────────────────────────────────

    public function testStockValidationResultAllowed(): void
    {
        $r = StockValidationResult::allowed();

        self::assertTrue($r->allowed);
        self::assertNull($r->warning);
        self::assertNull($r->reason);
    }

    public function testStockValidationResultWarning(): void
    {
        $r = StockValidationResult::warning('Stock bajo');

        self::assertTrue($r->allowed);
        self::assertSame('Stock bajo', $r->warning);
        self::assertNull($r->reason);
    }

    public function testStockValidationResultBlocked(): void
    {
        $r = StockValidationResult::blocked('Sin stock');

        self::assertFalse($r->allowed);
        self::assertNull($r->warning);
        self::assertSame('Sin stock', $r->reason);
    }

    // ── StockAuthorizationResult ──────────────────────────────────────────────

    public function testStockAuthorizationResultGranted(): void
    {
        $r = StockAuthorizationResult::granted();

        self::assertTrue($r->granted);
        self::assertNull($r->reason);
    }

    public function testStockAuthorizationResultDenied(): void
    {
        $r = StockAuthorizationResult::denied('Tenant no tiene permiso');

        self::assertFalse($r->granted);
        self::assertSame('Tenant no tiene permiso', $r->reason);
    }

    // ── MovementDraft ─────────────────────────────────────────────────────────

    public function testMovementDraftHoldsAllFields(): void
    {
        $draft = new MovementDraft(
            warehouseId: 'wh-1',
            productId: 'prod-1',
            movementType: StockMovementType::SALE,
            qtyDeltaBase: '-2.000000',
            unitId: 5,
            qtyOriginal: '2.000000',
            sourceType: 'sale',
            sourceId: 'sale-99',
            note: 'Venta al cliente',
            happenedAt: '2026-04-30T12:00:00',
        );

        self::assertSame('wh-1', $draft->warehouseId);
        self::assertSame('prod-1', $draft->productId);
        self::assertSame(StockMovementType::SALE, $draft->movementType);
        self::assertSame('-2.000000', $draft->qtyDeltaBase);
        self::assertSame(5, $draft->unitId);
        self::assertSame('Venta al cliente', $draft->note);
        self::assertSame('2026-04-30T12:00:00', $draft->happenedAt);
    }

    public function testMovementDraftNullableDefaults(): void
    {
        $draft = new MovementDraft(
            warehouseId: 'wh-1',
            productId: 'prod-1',
            movementType: StockMovementType::ADJUSTMENT,
            qtyDeltaBase: '5.000000',
            unitId: 1,
            qtyOriginal: '5.000000',
            sourceType: 'manual',
            sourceId: 'adj-1',
        );

        self::assertNull($draft->note);
        self::assertNull($draft->happenedAt);
    }

    // ── StockMovementRecorded ─────────────────────────────────────────────────

    public function testStockMovementRecordedHoldsAllFields(): void
    {
        $happenedAt = new DateTimeImmutable('2026-04-30T12:00:00');

        $event = new StockMovementRecorded(
            movementId: 'mov-uuid',
            warehouseId: 'wh-1',
            productId: 'prod-1',
            movementType: StockMovementType::PURCHASE,
            qtyDeltaBase: '10.000000',
            sourceType: 'purchase',
            sourceId: 'po-1',
            happenedAt: $happenedAt,
        );

        self::assertSame('mov-uuid', $event->movementId);
        self::assertSame('wh-1', $event->warehouseId);
        self::assertSame(StockMovementType::PURCHASE, $event->movementType);
        self::assertSame('10.000000', $event->qtyDeltaBase);
        self::assertSame($happenedAt, $event->happenedAt);
    }

    // ── ProductKind enum ──────────────────────────────────────────────────────

    public function testProductKindFromLegacyTypeIdPhysical(): void
    {
        self::assertSame(ProductKind::GOOD, ProductKind::fromLegacyTypeId(1));
    }

    public function testProductKindFromLegacyTypeIdServiceDefault(): void
    {
        self::assertSame(ProductKind::SERVICE, ProductKind::fromLegacyTypeId(2));
        self::assertSame(ProductKind::SERVICE, ProductKind::fromLegacyTypeId(99));
    }

    public function testProductKindFromProductTypeGoods(): void
    {
        self::assertSame(ProductKind::GOOD, ProductKind::fromProductType('STOCKABLE'));
        self::assertSame(ProductKind::GOOD, ProductKind::fromProductType('COMPOSITE'));
        self::assertSame(ProductKind::GOOD, ProductKind::fromProductType('CONSUMABLE'));
        self::assertSame(ProductKind::GOOD, ProductKind::fromProductType('stockable'));
    }

    public function testProductKindFromProductTypeDigital(): void
    {
        self::assertSame(ProductKind::DIGITAL, ProductKind::fromProductType('DIGITAL'));
    }

    public function testProductKindFromProductTypeServiceDefault(): void
    {
        self::assertSame(ProductKind::SERVICE, ProductKind::fromProductType('SERVICE'));
        self::assertSame(ProductKind::SERVICE, ProductKind::fromProductType('UNKNOWN'));
    }

    // ── ProductType enum ──────────────────────────────────────────────────────

    public function testProductTypeTracksInventory(): void
    {
        self::assertTrue(ProductType::STOCKABLE->tracksInventory());
        self::assertTrue(ProductType::CONSUMABLE->tracksInventory());
        self::assertTrue(ProductType::COMPOSITE->tracksInventory());
    }

    public function testProductTypeDoesNotTrackInventory(): void
    {
        self::assertFalse(ProductType::SERVICE->tracksInventory());
        self::assertFalse(ProductType::DIGITAL->tracksInventory());
    }
}
