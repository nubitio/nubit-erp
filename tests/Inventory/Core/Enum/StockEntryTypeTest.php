<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Enum;

use App\Inventory\Core\Enum\StockEntryType;
use App\Inventory\Core\Enum\StockMovementType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StockEntryType enum.
 *
 * Pure logic — no dependencies, no mocks.
 */
final class StockEntryTypeTest extends TestCase
{
    // ─── label() ─────────────────────────────────────────────────────────────

    public function testEachTypeHasANonEmptyLabel(): void
    {
        foreach (StockEntryType::cases() as $type) {
            self::assertNotEmpty($type->label(), "label() is empty for {$type->value}");
        }
    }

    // ─── movementTypes() ─────────────────────────────────────────────────────

    public function testReceiptMapsToManualIn(): void
    {
        self::assertSame([StockMovementType::MANUAL_IN], StockEntryType::RECEIPT->movementTypes());
    }

    public function testIssueMapsToManualOut(): void
    {
        self::assertSame([StockMovementType::MANUAL_OUT], StockEntryType::ISSUE->movementTypes());
    }

    public function testTransferMapsToBothTransferOutAndIn(): void
    {
        $types = StockEntryType::TRANSFER->movementTypes();
        self::assertContains(StockMovementType::TRANSFER_OUT, $types);
        self::assertContains(StockMovementType::TRANSFER_IN, $types);
        self::assertCount(2, $types);
    }

    public function testAdjustmentMapsToAdjustment(): void
    {
        self::assertSame([StockMovementType::ADJUSTMENT], StockEntryType::ADJUSTMENT->movementTypes());
    }

    // ─── Constraint flags ────────────────────────────────────────────────────

    public function testOnlyTransferRequiresTargetWarehouse(): void
    {
        self::assertTrue(StockEntryType::TRANSFER->requiresTargetWarehouse());
        self::assertFalse(StockEntryType::RECEIPT->requiresTargetWarehouse());
        self::assertFalse(StockEntryType::ISSUE->requiresTargetWarehouse());
        self::assertFalse(StockEntryType::ADJUSTMENT->requiresTargetWarehouse());
    }

    public function testOnlyAdjustmentRequiresReason(): void
    {
        self::assertTrue(StockEntryType::ADJUSTMENT->requiresReason());
        self::assertFalse(StockEntryType::RECEIPT->requiresReason());
        self::assertFalse(StockEntryType::ISSUE->requiresReason());
        self::assertFalse(StockEntryType::TRANSFER->requiresReason());
    }

    public function testOnlyAdjustmentUsesAbsoluteQty(): void
    {
        self::assertTrue(StockEntryType::ADJUSTMENT->usesAbsoluteQty());
        self::assertFalse(StockEntryType::RECEIPT->usesAbsoluteQty());
        self::assertFalse(StockEntryType::ISSUE->usesAbsoluteQty());
        self::assertFalse(StockEntryType::TRANSFER->usesAbsoluteQty());
    }

    public function testIssueAndTransferValidateStock(): void
    {
        self::assertTrue(StockEntryType::ISSUE->validatesStock());
        self::assertTrue(StockEntryType::TRANSFER->validatesStock());
        self::assertFalse(StockEntryType::RECEIPT->validatesStock());
        self::assertFalse(StockEntryType::ADJUSTMENT->validatesStock());
    }
}
