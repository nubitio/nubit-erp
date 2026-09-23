<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core;

use App\Efact\Enum\StockPolicy;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Dto\StockAuthorizationResult;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\LotTrackingStrategy;
use App\Inventory\Core\Enum\ProductKind;
use App\Inventory\Core\Enum\ProductType;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Event\PurchaseReceived;
use App\Inventory\Core\Event\SaleCompleted;
use App\Inventory\Core\Event\StockMovementRecorded;
use PHPUnit\Framework\TestCase;

/**
 * Pure-unit tests for all Inventory Core value objects, enums, DTOs, and events.
 * No I/O, no DB, no Symfony kernel.
 */
final class InventoryCoreValueObjectsTest extends TestCase
{
    // ── InventoryBehavior ─────────────────────────────────────────────────────

    public function testInventoryBehaviorTracksInventory(): void
    {
        self::assertTrue(InventoryBehavior::TRACKED->tracksInventory());
        self::assertTrue(InventoryBehavior::COMPOSITE->tracksInventory());
        self::assertFalse(InventoryBehavior::NOT_TRACKED->tracksInventory());
    }

    public function testInventoryBehaviorGeneratesOwnMovements(): void
    {
        self::assertTrue(InventoryBehavior::TRACKED->generatesOwnMovements());
        self::assertFalse(InventoryBehavior::COMPOSITE->generatesOwnMovements());
        self::assertFalse(InventoryBehavior::NOT_TRACKED->generatesOwnMovements());
    }

    public function testInventoryBehaviorRequiresBomExplosion(): void
    {
        self::assertFalse(InventoryBehavior::TRACKED->requiresBomExplosion());
        self::assertFalse(InventoryBehavior::NOT_TRACKED->requiresBomExplosion());
        self::assertTrue(InventoryBehavior::COMPOSITE->requiresBomExplosion());
    }

    public function testInventoryBehaviorFromProductType(): void
    {
        self::assertSame(InventoryBehavior::COMPOSITE, InventoryBehavior::fromProductType('COMPOSITE'));
        self::assertSame(InventoryBehavior::NOT_TRACKED, InventoryBehavior::fromProductType('SERVICE'));
        self::assertSame(InventoryBehavior::NOT_TRACKED, InventoryBehavior::fromProductType('DIGITAL'));
        self::assertSame(InventoryBehavior::TRACKED, InventoryBehavior::fromProductType('STOCKABLE'));
        self::assertSame(InventoryBehavior::TRACKED, InventoryBehavior::fromProductType('anything_else'));
        // case-insensitive
        self::assertSame(InventoryBehavior::NOT_TRACKED, InventoryBehavior::fromProductType('service'));
    }

    // ── ProductKind ───────────────────────────────────────────────────────────

    public function testProductKindFromLegacyTypeId(): void
    {
        self::assertSame(ProductKind::GOOD, ProductKind::fromLegacyTypeId(1));
        self::assertSame(ProductKind::SERVICE, ProductKind::fromLegacyTypeId(2));
        self::assertSame(ProductKind::SERVICE, ProductKind::fromLegacyTypeId(99));
    }

    public function testProductKindFromProductType(): void
    {
        self::assertSame(ProductKind::GOOD, ProductKind::fromProductType('STOCKABLE'));
        self::assertSame(ProductKind::GOOD, ProductKind::fromProductType('COMPOSITE'));
        self::assertSame(ProductKind::GOOD, ProductKind::fromProductType('CONSUMABLE'));
        self::assertSame(ProductKind::DIGITAL, ProductKind::fromProductType('DIGITAL'));
        self::assertSame(ProductKind::SERVICE, ProductKind::fromProductType('SERVICE'));
        self::assertSame(ProductKind::SERVICE, ProductKind::fromProductType('unknown'));
    }

    // ── ProductType ───────────────────────────────────────────────────────────

    public function testProductTypeTracksInventory(): void
    {
        self::assertTrue(ProductType::STOCKABLE->tracksInventory());
        self::assertTrue(ProductType::CONSUMABLE->tracksInventory());
        self::assertTrue(ProductType::COMPOSITE->tracksInventory());
        self::assertFalse(ProductType::SERVICE->tracksInventory());
        self::assertFalse(ProductType::DIGITAL->tracksInventory());
    }

    // ── StockMovementType ─────────────────────────────────────────────────────

    public function testStockMovementTypeValues(): void
    {
        self::assertSame('PURCHASE_IN', StockMovementType::PURCHASE->value);
        self::assertSame('SALE_OUT', StockMovementType::SALE->value);
        self::assertSame('ADJUSTMENT', StockMovementType::ADJUSTMENT->value);
        self::assertSame('TRANSFER_IN', StockMovementType::TRANSFER_IN->value);
        self::assertSame('TRANSFER_OUT', StockMovementType::TRANSFER_OUT->value);
        self::assertSame('RECIPE_CONSUMPTION', StockMovementType::RECIPE_CONSUMPTION->value);
    }

    // ── StockPolicy ───────────────────────────────────────────────────────────

    public function testStockPolicyValues(): void
    {
        self::assertSame('STRICT', StockPolicy::STRICT->value);
        self::assertSame('WARNING_ONLY', StockPolicy::WARNING_ONLY->value);
        self::assertSame('ALLOW_NEGATIVE', StockPolicy::ALLOW_NEGATIVE->value);
    }

    // ── LotTrackingStrategy ───────────────────────────────────────────────────

    public function testLotTrackingStrategyValues(): void
    {
        self::assertSame('NONE', LotTrackingStrategy::NONE->value);
        self::assertSame('FIFO', LotTrackingStrategy::FIFO->value);
        self::assertSame('FEFO', LotTrackingStrategy::FEFO->value);
    }

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

    public function testStockAuthorizationGranted(): void
    {
        $r = StockAuthorizationResult::granted();
        self::assertTrue($r->granted);
        self::assertNull($r->reason);
    }

    public function testStockAuthorizationDenied(): void
    {
        $r = StockAuthorizationResult::denied('Cuota excedida');
        self::assertFalse($r->granted);
        self::assertSame('Cuota excedida', $r->reason);
    }

    // ── MovementDraft ─────────────────────────────────────────────────────────

    public function testMovementDraftHoldsAllFields(): void
    {
        $draft = new MovementDraft(
            warehouseId: 'wh-1',
            productId: 'prod-1',
            movementType: StockMovementType::SALE,
            qtyDeltaBase: '-2.000000',
            unitId: 0,
            qtyOriginal: '2',
            sourceType: 'sale',
            sourceId: 'sale-uuid',
            note: 'Venta directa',
            happenedAt: '2026-04-30 10:00:00',
        );

        self::assertSame('wh-1', $draft->warehouseId);
        self::assertSame('prod-1', $draft->productId);
        self::assertSame(StockMovementType::SALE, $draft->movementType);
        self::assertSame('-2.000000', $draft->qtyDeltaBase);
        self::assertSame(0, $draft->unitId);
        self::assertSame('2', $draft->qtyOriginal);
        self::assertSame('sale', $draft->sourceType);
        self::assertSame('sale-uuid', $draft->sourceId);
        self::assertSame('Venta directa', $draft->note);
        self::assertSame('2026-04-30 10:00:00', $draft->happenedAt);
    }

    public function testMovementDraftOptionalFieldsDefault(): void
    {
        $draft = new MovementDraft(
            warehouseId: 'wh-1',
            productId: 'prod-1',
            movementType: StockMovementType::PURCHASE,
            qtyDeltaBase: '5.000000',
            unitId: 0,
            qtyOriginal: '5',
            sourceType: 'purchase',
            sourceId: 'po-1',
        );

        self::assertNull($draft->note);
        self::assertNull($draft->happenedAt);
    }

    // ── Domain Events ─────────────────────────────────────────────────────────

    public function testSaleCompletedEvent(): void
    {
        $at = new \DateTimeImmutable('2026-04-30 10:00:00');
        $e = new SaleCompleted('sale-1', 'wh-1', $at);

        self::assertSame('sale-1', $e->saleId);
        self::assertSame('wh-1', $e->warehouseId);
        self::assertSame($at, $e->happenedAt);
    }

    public function testPurchaseReceivedEvent(): void
    {
        $at = new \DateTimeImmutable('2026-04-30 11:00:00');
        $e = new PurchaseReceived('po-1', 'wh-2', $at);

        self::assertSame('po-1', $e->purchaseId);
        self::assertSame('wh-2', $e->warehouseId);
        self::assertSame($at, $e->happenedAt);
    }

    public function testStockMovementRecordedEvent(): void
    {
        $at = new \DateTimeImmutable('2026-04-30 12:00:00');
        $e = new StockMovementRecorded(
            movementId: 'mv-1',
            warehouseId: 'wh-1',
            productId: 'prod-1',
            movementType: StockMovementType::SALE,
            qtyDeltaBase: '-3.000000',
            sourceType: 'sale',
            sourceId: 'sale-42',
            happenedAt: $at,
        );

        self::assertSame('mv-1', $e->movementId);
        self::assertSame(StockMovementType::SALE, $e->movementType);
        self::assertSame('-3.000000', $e->qtyDeltaBase);
    }
}
