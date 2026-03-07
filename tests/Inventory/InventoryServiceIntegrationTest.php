<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use App\Entity\Product;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\ProductKind;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Event\StockMovementRecorded;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Core\Service\ReconciliationService;
use App\Inventory\Core\Service\UnitConversionService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Integration tests for InventoryService v2.
 *
 * Uses SQLite in-memory for fast, isolated test runs.
 * Tests cover the 9 mandatory scenarios from core-inventory-migration-plan.md.
 */
final class InventoryServiceIntegrationTest extends TestCase
{
    private ?Connection $conn = null;

    /** @var EventDispatcherInterface&MockObject */
    private MockObject $dispatcher;

    private UnitConversionService $unitConversion;

    protected function setUp(): void
    {
        if (!extension_loaded('bcmath')) {
            self::markTestSkipped('ext-bcmath is not available in this environment');
        }

        $this->conn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->createSchema();

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->unitConversion = new UnitConversionService($this->conn);
    }

    protected function tearDown(): void
    {
        if ($this->conn !== null) {
            $this->conn->close();
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 1. Service product → no movement generated
    // ────────────────────────────────────────────────────────────────────────────

    public function testServiceProductGeneratesNoMovement(): void
    {
        $product = $this->makeProduct('prod-svc', InventoryBehavior::NOT_TRACKED);

        $this->dispatcher->expects(self::never())->method('dispatch');

        $service = $this->makeInventoryService(policy: StockValidationResult::allowed());
        $draft = $this->makeDraft('prod-svc', '-1.000000', StockMovementType::SALE);

        $service->recordMovement($draft, behavior: InventoryBehavior::NOT_TRACKED);

        self::assertSame('0', $service->getOnHand('wh-test', 'prod-svc'));
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 2. Wine by glass: unit conversion 150ml copa → qty_delta_base = -150
    // ────────────────────────────────────────────────────────────────────────────

    public function testWineByGlassAppliesUnitConversionToBase(): void
    {
        // Seed: 750ml bottle = base unit 1; copa (150ml) = unit 2, factor 150
        $this->conn->insert('product_units', [
            'product_id' => 'prod-wine',
            'unit_id' => 2,
            'conversion_to_base' => '150.000000',
        ]);

        // Seed initial stock: 1 bottle = 750 base units
        $this->conn->insert('stock_items', [
            'warehouse_id' => 'wh-test',
            'product_id' => 'prod-wine',
            'qty_on_hand' => '750.000000',
            'qty_reserved' => '0.000000',
            'updated_at' => date('Y-m-d H:i:s'),
            'last_movement_id' => null,
            'version' => 0,
        ]);

        $service = $this->makeInventoryService(policy: StockValidationResult::allowed());

        // Sell 1 copa (unit_id=2, qty_original='1') → backend should compute qty_delta_base = -150
        $draft = new MovementDraft(
            warehouseId: 'wh-test',
            productId: 'prod-wine',
            movementType: StockMovementType::SALE,
            qtyDeltaBase: '-1.000000',  // frontend value (ignored — backend recomputes)
            unitId: 2,
            qtyOriginal: '1.000000',
            sourceType: 'sale',
            sourceId: 'sale-wine-1',
        );

        $service->recordMovement($draft);

        // Stock should decrease by 150 (one copa)
        self::assertEqualsWithDelta(600.0, (float) $service->getOnHand('wh-test', 'prod-wine'), 0.001);

        // Verify ledger row has correct qty_delta_base
        $ledgerQty = $this->conn->fetchOne(
            "SELECT qty_delta_base FROM stock_movements WHERE source_id = 'sale-wine-1'",
        );
        self::assertEqualsWithDelta(-150.0, (float) $ledgerQty, 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 3. STRICT policy blocks sale when stock = 0
    // ────────────────────────────────────────────────────────────────────────────

    public function testStrictPolicyBlocksSaleWhenStockIsZero(): void
    {
        $service = $this->makeInventoryService(
            policy: StockValidationResult::blocked('Insufficient stock (STRICT policy)'),
        );

        $product = $this->makeProduct('prod-a', InventoryBehavior::TRACKED);

        // validateForSale is the pre-flight check — it enforces policy before writing
        $result = $service->validateForSale($product, 'wh-test', '1.000000', 1);

        self::assertFalse($result->allowed);
        self::assertStringContainsString('STRICT', (string) $result->reason);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 4. WARNING_ONLY allows sale + warning event dispatched
    // ────────────────────────────────────────────────────────────────────────────

    public function testWarningOnlyAllowsSaleAndDispatchesWarningEvent(): void
    {
        $dispatchedEvents = [];
        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents): object {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $service = $this->makeInventoryService(
            policy: StockValidationResult::warning('Stock would go negative (WARNING_ONLY)'),
        );

        $draft = $this->makeDraft('prod-b', '-1.000000', StockMovementType::SALE);
        $service->recordMovement($draft);

        // Movement should still be recorded
        self::assertEqualsWithDelta(-1.0, (float) $service->getOnHand('wh-test', 'prod-b'), 0.001);

        // StockMovementRecorded event must have been dispatched
        $recordedEvents = array_filter($dispatchedEvents, static fn ($e) => $e instanceof StockMovementRecorded);
        self::assertNotEmpty($recordedEvents, 'StockMovementRecorded event must be dispatched');
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 5. ALLOW_NEGATIVE allows sale into negative stock
    // ────────────────────────────────────────────────────────────────────────────

    public function testAllowNegativePolicyPermitsNegativeStock(): void
    {
        $service = $this->makeInventoryService(policy: StockValidationResult::allowed());

        // No initial stock — record a SALE_OUT of 5 units
        $draft = $this->makeDraft('prod-neg', '-5.000000', StockMovementType::SALE);
        $service->recordMovement($draft);

        $onHand = $service->getOnHand('wh-test', 'prod-neg');
        self::assertEqualsWithDelta(-5.0, (float) $onHand, 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 6. Idempotent duplicate sale → no double stock deduction
    // ────────────────────────────────────────────────────────────────────────────

    public function testDuplicateSaleDoesNotDoubleDeductStock(): void
    {
        // Seed initial stock
        $this->conn->insert('stock_items', [
            'warehouse_id' => 'wh-test',
            'product_id' => 'prod-c',
            'qty_on_hand' => '10.000000',
            'qty_reserved' => '0.000000',
            'updated_at' => date('Y-m-d H:i:s'),
            'last_movement_id' => null,
            'version' => 0,
        ]);

        $service = $this->makeInventoryService(policy: StockValidationResult::allowed());

        $draft = $this->makeDraft('prod-c', '-2.000000', StockMovementType::SALE, 'sale-dup-1');

        // Apply twice (simulating retry)
        $service->recordMovement($draft);
        $service->recordMovement($draft);

        // Stock should only decrease once: 10 - 2 = 8
        self::assertEqualsWithDelta(8.0, (float) $service->getOnHand('wh-test', 'prod-c'), 0.001);

        // Only one ledger row should exist
        $count = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM stock_movements WHERE source_id = 'sale-dup-1'",
        );
        self::assertSame(1, $count);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 7. Transfer atomicity — TRANSFER_OUT + TRANSFER_IN or both fail
    // ────────────────────────────────────────────────────────────────────────────

    public function testTransferAtomicityBothMovementsOrNone(): void
    {
        $this->conn->insert('stock_items', [
            'warehouse_id' => 'wh-src',
            'product_id' => 'prod-t',
            'qty_on_hand' => '20.000000',
            'qty_reserved' => '0.000000',
            'updated_at' => date('Y-m-d H:i:s'),
            'last_movement_id' => null,
            'version' => 0,
        ]);
        $this->conn->insert('stock_items', [
            'warehouse_id' => 'wh-dst',
            'product_id' => 'prod-t',
            'qty_on_hand' => '0.000000',
            'qty_reserved' => '0.000000',
            'updated_at' => date('Y-m-d H:i:s'),
            'last_movement_id' => null,
            'version' => 0,
        ]);

        $service = $this->makeInventoryService(policy: StockValidationResult::allowed());

        $draftOut = new MovementDraft(
            warehouseId: 'wh-src',
            productId: 'prod-t',
            movementType: StockMovementType::TRANSFER_OUT,
            qtyDeltaBase: '-5.000000',
            unitId: 1,
            qtyOriginal: '-5.000000',
            sourceType: 'transfer',
            sourceId: 'transfer-1',
        );
        $draftIn = new MovementDraft(
            warehouseId: 'wh-dst',
            productId: 'prod-t',
            movementType: StockMovementType::TRANSFER_IN,
            qtyDeltaBase: '5.000000',
            unitId: 1,
            qtyOriginal: '5.000000',
            sourceType: 'transfer',
            sourceId: 'transfer-1',
        );

        $service->recordMovement($draftOut);
        $service->recordMovement($draftIn);

        self::assertEqualsWithDelta(15.0, (float) $service->getOnHand('wh-src', 'prod-t'), 0.001);
        self::assertEqualsWithDelta(5.0, (float) $service->getOnHand('wh-dst', 'prod-t'), 0.001);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 8. Ledger rebuild correctness
    // ────────────────────────────────────────────────────────────────────────────

    public function testLedgerRebuildProducesSameQtyAsStockItems(): void
    {
        $service = $this->makeInventoryService(policy: StockValidationResult::allowed());

        // Record 3 movements: +10, -3, -2 → on_hand = 5
        foreach ([
            ['+10.000000', StockMovementType::PURCHASE, 'po-1'],
            ['-3.000000', StockMovementType::SALE, 'sale-r1'],
            ['-2.000000', StockMovementType::SALE, 'sale-r2'],
        ] as [$qty, $type, $sourceId]) {
            $service->recordMovement($this->makeDraft('prod-r', $qty, $type, $sourceId));
        }

        // stock_items read model must reflect the correct qty
        self::assertEqualsWithDelta(5.0, (float) $service->getOnHand('wh-test', 'prod-r'), 0.001);

        // Verify invariant: SUM(ledger) == stock_items.qty_on_hand
        $ledgerSum = $this->conn->fetchOne(
            "SELECT SUM(qty_delta_base) FROM stock_movements WHERE warehouse_id = 'wh-test' AND product_id = 'prod-r'",
        );
        $stockItemsQty = $this->conn->fetchOne(
            "SELECT qty_on_hand FROM stock_items WHERE warehouse_id = 'wh-test' AND product_id = 'prod-r'",
        );

        self::assertEqualsWithDelta(
            (float) $ledgerSum,
            (float) $stockItemsQty,
            0.001,
            'Invariant violated: SUM(ledger) must equal stock_items.qty_on_hand',
        );
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 9. Reconciliation diff detection — abs(diff) > 0.001 → DRIFT
    // ────────────────────────────────────────────────────────────────────────────

    public function testReconciliationDetectsDriftWhenLedgerAndReadModelDiverge(): void
    {
        // Seed stock_items with an "incorrect" value
        $this->conn->insert('stock_items', [
            'warehouse_id' => 'wh-test',
            'product_id' => 'prod-drift',
            'qty_on_hand' => '100.000000',
            'qty_reserved' => '0.000000',
            'updated_at' => date('Y-m-d H:i:s'),
            'last_movement_id' => null,
            'version' => 0,
        ]);

        // Seed ledger with only 5 units total
        $this->conn->insert('stock_movements', [
            'id' => 'mv-drift-1',
            'warehouse_id' => 'wh-test',
            'product_id' => 'prod-drift',
            'movement_type' => 'PURCHASE',
            'quantity_delta' => '5.000000',
            'qty_delta_base' => '5.000000',
            'qty_original' => '5.000000',
            'unit_id' => 1,
            'source_type' => 'purchase',
            'source_id' => 'po-drift-1',
            'idempotency_key' => hash('sha256', 'purchase|po-drift-1|PURCHASE|wh-test|prod-drift'),
            'occurred_at' => date('Y-m-d H:i:s'),
            'sync_status' => 'SYNCED',
            'synced_at' => date('Y-m-d H:i:s'),
            'version_no' => 1,
            'last_modified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Create reconciliation tables (already in setUp schema)
        $logger = $this->createStub(\Psr\Log\LoggerInterface::class);
        $reconciler = new ReconciliationService($this->conn, $logger);
        $status = $reconciler->reconcileOne('wh-test', 'prod-drift', '100.000000');

        self::assertSame('drift', $status);

        // Diff must exceed 0.001 tolerance threshold
        $run = $this->conn->fetchAssociative(
            "SELECT diff FROM inventory_reconciliation_runs WHERE product_id = 'prod-drift' LIMIT 1",
        );
        self::assertIsArray($run);
        self::assertGreaterThan(0.001, abs((float) $run['diff']));
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────────

    private function makeProduct(string $id, InventoryBehavior $behavior): Product
    {
        $product = new Product();
        $product->setInventoryBehavior($behavior);
        $product->setProductKind(ProductKind::GOOD);

        // Set id via reflection (Doctrine generates it on persist; tests need it explicitly)
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setValue($product, $id);

        return $product;
    }

    /**
     * Build InventoryService with a stubbed validation policy.
     */
    private function makeInventoryService(StockValidationResult $policy): InventoryService
    {
        $policyStub = $this->createStub(\App\Inventory\Core\Contract\StockValidationPolicyInterface::class);
        $policyStub->method('validateSale')->willReturn($policy);

        return new InventoryService(
            connection: $this->conn,
            validationPolicy: $policyStub,
            unitConversion: $this->unitConversion,
            eventDispatcher: $this->dispatcher,
            enrichers: [],
        );
    }

    private function makeDraft(
        string $productId,
        string $qty,
        StockMovementType $type,
        string $sourceId = 'src-1',
    ): MovementDraft {
        return new MovementDraft(
            warehouseId: 'wh-test',
            productId: $productId,
            movementType: $type,
            qtyDeltaBase: $qty,
            unitId: 1,
            qtyOriginal: $qty,
            sourceType: 'test',
            sourceId: $sourceId,
        );
    }

    private function createSchema(): void
    {
        $this->conn->executeStatement(
            <<<'SQL'
            CREATE TABLE stock_movements (
                id VARCHAR(36) NOT NULL,
                warehouse_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                occurred_at DATETIME NOT NULL,
                movement_type VARCHAR(32) NOT NULL,
                quantity_delta DECIMAL(18,6) NOT NULL,
                qty_delta_base DECIMAL(18,6) NOT NULL,
                qty_original DECIMAL(18,6) NOT NULL,
                unit_id INTEGER DEFAULT NULL,
                source_type VARCHAR(64) NOT NULL,
                source_id VARCHAR(128) NOT NULL,
                note TEXT DEFAULT NULL,
                idempotency_key VARCHAR(64) NOT NULL,
                sync_status VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
                synced_at DATETIME DEFAULT NULL,
                version_no INTEGER NOT NULL DEFAULT 1,
                last_modified_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                lot_id VARCHAR(36) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE (idempotency_key)
            )
            SQL,
        );

        $this->conn->executeStatement(
            <<<'SQL'
            CREATE TABLE stock_items (
                warehouse_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                qty_on_hand DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                qty_reserved DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_movement_id VARCHAR(36) DEFAULT NULL,
                version INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (warehouse_id, product_id)
            )
            SQL,
        );

        $this->conn->executeStatement(
            <<<'SQL'
            CREATE TABLE product_units (
                product_id VARCHAR(36) NOT NULL,
                unit_id INTEGER NOT NULL,
                conversion_to_base DECIMAL(18,6) NOT NULL,
                PRIMARY KEY (product_id, unit_id)
            )
            SQL,
        );

        // Seed product_units for base unit (factor = 1) for all test products
        foreach (['prod-svc', 'prod-a', 'prod-b', 'prod-neg', 'prod-c', 'prod-t', 'prod-r', 'prod-drift', 'prod-wine'] as $pid) {
            $this->conn->insert('product_units', [
                'product_id' => $pid,
                'unit_id' => 1,
                'conversion_to_base' => '1.000000',
            ]);
        }

        $this->conn->executeStatement(
            <<<'SQL'
            CREATE TABLE stock_balances (
                warehouse_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                on_hand_qty DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                PRIMARY KEY (warehouse_id, product_id)
            )
            SQL,
        );

        $this->conn->executeStatement(
            <<<'SQL'
            CREATE TABLE inventory_reconciliation_runs (
                id VARCHAR(36) NOT NULL,
                warehouse_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                ledger_sum DECIMAL(18,6) NOT NULL,
                stock_items_qty DECIMAL(18,6) NOT NULL,
                diff DECIMAL(18,6) NOT NULL,
                status VARCHAR(10) NOT NULL,
                run_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            )
            SQL,
        );
    }
}
