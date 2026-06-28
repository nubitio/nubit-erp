<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\State;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Core\Service\UnitConversionService;
use App\Inventory\Entity\Warehouse;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use App\Purchasing\State\PurchaseVoidService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * Integration tests for PurchaseVoidService.
 *
 * Covers:
 *   - Single-item purchase → PURCHASE_VOID reverses stock
 *   - Multi-item purchase → all lines reversed
 *   - Idempotency: calling reverse() twice does not double-deduct
 *   - NOT_TRACKED product → skipped silently
 *   - Zero-qty item → skipped
 *   - Purchase with no warehouse → no-op
 *   - Movement type is PURCHASE_VOID
 *   - Net ledger after original PURCHASE_IN + PURCHASE_VOID = 0
 */
#[AllowMockObjectsWithoutExpectations]
final class PurchaseVoidServiceTest extends TestCase
{
    private Connection $conn;
    private InventoryService $inventoryService;

    protected function setUp(): void
    {
        $this->conn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->createSchema();

        $policy = $this->createMock(StockValidationPolicyInterface::class);
        $policy->method('validateSale')->willReturn(StockValidationResult::allowed());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->inventoryService = new InventoryService(
            $this->conn,
            $policy,
            new UnitConversionService($this->conn),
            $dispatcher,
        );
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }

    // ─── Core behaviour ───────────────────────────────────────────────────────

    public function testReversesStockForSingleItem(): void
    {
        $this->seedProductUnit('prod-a');
        $purchase = $this->makePurchase('p-1', 'wh-1', [['prod-a', '10.000']]);

        // First record the original purchase receipt
        (new \App\Purchasing\State\PurchaseInventoryService($this->inventoryService))
            ->recordPurchaseReceipt($purchase);

        self::assertEqualsWithDelta(10.0, $this->getOnHand('wh-1', 'prod-a'), 0.000001);

        // Now void it
        $this->service()->reverse($purchase);

        // Stock back to zero
        self::assertEqualsWithDelta(0.0, $this->getOnHand('wh-1', 'prod-a'), 0.000001);

        // A PURCHASE_VOID movement exists
        $type = $this->conn->fetchOne(
            "SELECT movement_type FROM stock_movements WHERE source_type = 'PURCHASE_VOID'",
        );
        self::assertSame(StockMovementType::PURCHASE_VOID->value, $type);
    }

    public function testNetLedgerIsZeroAfterReceiptAndVoid(): void
    {
        $this->seedProductUnit('prod-a');
        $purchase = $this->makePurchase('p-2', 'wh-1', [['prod-a', '7.500']]);

        $receiptSvc = new \App\Purchasing\State\PurchaseInventoryService($this->inventoryService);
        $receiptSvc->recordPurchaseReceipt($purchase);
        $this->service()->reverse($purchase);

        $net = $this->conn->fetchOne(
            "SELECT SUM(qty_delta_base) FROM stock_movements WHERE warehouse_id = 'wh-1' AND product_id = 'prod-a'",
        );
        self::assertEqualsWithDelta(0.0, (float) $net, 0.000001);
    }

    public function testReversesMultipleItems(): void
    {
        $this->seedProductUnit('prod-a');
        $this->seedProductUnit('prod-b');

        $purchase = $this->makePurchase('p-3', 'wh-1', [
            ['prod-a', '5.000'],
            ['prod-b', '3.000'],
        ]);

        $receiptSvc = new \App\Purchasing\State\PurchaseInventoryService($this->inventoryService);
        $receiptSvc->recordPurchaseReceipt($purchase);
        $this->service()->reverse($purchase);

        self::assertEqualsWithDelta(0.0, $this->getOnHand('wh-1', 'prod-a'), 0.000001);
        self::assertEqualsWithDelta(0.0, $this->getOnHand('wh-1', 'prod-b'), 0.000001);

        $voidCount = $this->conn->fetchOne(
            "SELECT COUNT(*) FROM stock_movements WHERE source_type = 'PURCHASE_VOID'",
        );
        self::assertSame(2, (int) $voidCount);
    }

    public function testIdempotentOnDoubleVoid(): void
    {
        $this->seedProductUnit('prod-a');
        $purchase = $this->makePurchase('p-4', 'wh-1', [['prod-a', '6.000']]);

        $receiptSvc = new \App\Purchasing\State\PurchaseInventoryService($this->inventoryService);
        $receiptSvc->recordPurchaseReceipt($purchase);

        $svc = $this->service();
        $svc->reverse($purchase); // first void
        $svc->reverse($purchase); // second call — must be no-op (idempotency_key collision)

        // Only one PURCHASE_VOID in ledger
        $voidCount = $this->conn->fetchOne(
            "SELECT COUNT(*) FROM stock_movements WHERE source_type = 'PURCHASE_VOID'",
        );
        self::assertSame(1, (int) $voidCount);

        // Stock = 0, not negative
        self::assertEqualsWithDelta(0.0, $this->getOnHand('wh-1', 'prod-a'), 0.000001);
    }

    public function testSkipsNotTrackedProduct(): void
    {
        $purchase = $this->makePurchase('p-5', 'wh-1', []);

        $item = new PurchaseItem();
        $item->setProduct($this->makeProduct('prod-svc', trackInventory: false));
        $item->setQty('99.000');
        $purchase->addItem($item);

        $this->service()->reverse($purchase);

        $count = $this->conn->fetchOne("SELECT COUNT(*) FROM stock_movements");
        self::assertSame(0, (int) $count);
    }

    public function testSkipsZeroQtyItem(): void
    {
        $this->seedProductUnit('prod-a');
        $purchase = $this->makePurchase('p-6', 'wh-1', [['prod-a', '0.000']]);

        $this->service()->reverse($purchase);

        $count = $this->conn->fetchOne("SELECT COUNT(*) FROM stock_movements");
        self::assertSame(0, (int) $count);
    }

    public function testNoOpWhenNoWarehouse(): void
    {
        $purchase = new Purchase();
        $purchase->setId('p-7');
        // No warehouse set

        $this->service()->reverse($purchase);

        $count = $this->conn->fetchOne("SELECT COUNT(*) FROM stock_movements");
        self::assertSame(0, (int) $count);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function service(): PurchaseVoidService
    {
        return new PurchaseVoidService($this->inventoryService, new NullLogger());
    }

    /**
     * @param array<array{0: string, 1: string}> $items
     */
    private function makePurchase(string $id, string $warehouseId, array $items): Purchase
    {
        $purchase = new Purchase();
        $purchase->setId($id);

        $wh = new Warehouse();
        $ref = new \ReflectionProperty(Warehouse::class, 'id');
        $ref->setValue($wh, $warehouseId);
        $purchase->setWarehouse($wh);
        $purchase->setIssuedAt(new \DateTimeImmutable('2026-01-01 10:00:00'));

        $lineNo = 1;
        foreach ($items as [$productId, $qty]) {
            $item = new PurchaseItem();
            $item->setLineNo($lineNo++);
            $item->setProduct($this->makeProduct($productId));
            $item->setQty($qty);
            $purchase->addItem($item);

            $this->seedProductUnit($productId);
        }

        return $purchase;
    }

    private function makeProduct(string $id, bool $trackInventory = true): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setValue($product, $id);
        $product->setTrackInventory($trackInventory);
        $product->setName('Producto ' . $id);

        return $product;
    }

    private function seedProductUnit(string $productId): void
    {
        $exists = (int) $this->conn->fetchOne(
            'SELECT COUNT(*) FROM product_units WHERE product_id = ? AND unit_id = 1',
            [$productId],
        );
        if (!$exists) {
            $this->conn->insert('product_units', [
                'product_id'         => $productId,
                'unit_id'            => 1,
                'conversion_to_base' => '1.000000',
            ]);
        }
    }

    private function getOnHand(string $warehouseId, string $productId): float
    {
        return (float) ($this->conn->fetchOne(
            'SELECT qty_on_hand FROM stock_items WHERE warehouse_id = ? AND product_id = ?',
            [$warehouseId, $productId],
        ) ?: 0);
    }

    private function createSchema(): void
    {
        $this->conn->executeStatement(<<<'SQL'
            CREATE TABLE stock_movements (
                id               VARCHAR(36)   NOT NULL,
                warehouse_id     VARCHAR(36)   NOT NULL,
                product_id       VARCHAR(36)   NOT NULL,
                occurred_at      DATETIME      NOT NULL,
                movement_type    VARCHAR(32)   NOT NULL,
                quantity_delta   DECIMAL(18,6) NOT NULL,
                qty_delta_base   DECIMAL(18,6) NOT NULL,
                qty_original     DECIMAL(18,6) NOT NULL,
                unit_id          INTEGER       DEFAULT NULL,
                source_type      VARCHAR(64)   NOT NULL,
                source_id        VARCHAR(255)  NOT NULL,
                note             TEXT          DEFAULT NULL,
                idempotency_key  VARCHAR(64)   NOT NULL UNIQUE,
                sync_status      VARCHAR(20)   NOT NULL DEFAULT 'SYNCED',
                synced_at        DATETIME      DEFAULT NULL,
                version_no       INTEGER       NOT NULL DEFAULT 1,
                last_modified_at DATETIME      NOT NULL,
                created_at       DATETIME      NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->conn->executeStatement(<<<'SQL'
            CREATE TABLE stock_items (
                warehouse_id     VARCHAR(36)   NOT NULL,
                product_id       VARCHAR(36)   NOT NULL,
                qty_on_hand      DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                qty_reserved     DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_movement_id VARCHAR(36)   DEFAULT NULL,
                version          INTEGER       NOT NULL DEFAULT 0,
                PRIMARY KEY (warehouse_id, product_id)
            )
        SQL);

        $this->conn->executeStatement(<<<'SQL'
            CREATE TABLE stock_balances (
                warehouse_id VARCHAR(36)   NOT NULL,
                product_id   VARCHAR(36)   NOT NULL,
                on_hand_qty  DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
                PRIMARY KEY (warehouse_id, product_id)
            )
        SQL);

        $this->conn->executeStatement(<<<'SQL'
            CREATE TABLE product_units (
                product_id          VARCHAR(36)   NOT NULL,
                unit_id             INTEGER       NOT NULL,
                conversion_to_base  DECIMAL(18,6) NOT NULL,
                PRIMARY KEY (product_id, unit_id)
            )
        SQL);
    }
}
