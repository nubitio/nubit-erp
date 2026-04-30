<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Adapter;

use App\Catalog\Entity\Product;
use App\Inventory\Adapter\InventoryServiceSalesInventoryPort;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Core\Service\UnitConversionService;
use App\Inventory\Entity\Warehouse;
use App\Sales\Entity\SaleItem;
use App\Sales\Entity\SaleRecord;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class InventoryServiceSalesInventoryPortTest extends TestCase
{
    private Connection $conn;
    private InventoryServiceSalesInventoryPort $port;

    protected function setUp(): void
    {
        $this->conn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->createSchema();

        $policy = $this->createStub(StockValidationPolicyInterface::class);
        $policy->method('validateSale')->willReturn(StockValidationResult::allowed());

        $dispatcher = $this->createStub(EventDispatcherInterface::class);

        $this->port = new InventoryServiceSalesInventoryPort(new InventoryService(
            connection: $this->conn,
            validationPolicy: $policy,
            unitConversion: new UnitConversionService($this->conn),
            eventDispatcher: $dispatcher,
            enrichers: [],
        ));
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }

    public function testGetStockDelegatesToInventoryServiceOnHand(): void
    {
        $this->conn->insert('stock_items', [
            'warehouse_id' => 'wh-1',
            'product_id' => 'prod-1',
            'qty_on_hand' => '12.500000',
            'qty_reserved' => '0.000000',
            'updated_at' => '2026-04-29 10:00:00',
            'version' => 0,
        ]);

        self::assertSame(12.5, $this->port->getStockForProductInWarehouse(
            $this->product('prod-1'),
            $this->warehouse('wh-1'),
        ));
    }

    public function testSaleItemMovementIsMappedToInventoryV2Draft(): void
    {
        $this->port->applyMovementFromSaleItem(
            $this->sale(tipoId: 2),
            $this->item($this->product('prod-1'), 3.0),
            2,
        );

        self::assertEqualsWithDelta(-3.0, (float) $this->conn->fetchOne('SELECT qty_on_hand FROM stock_items WHERE product_id = ?', ['prod-1']), 0.001);

        $movement = $this->conn->fetchAssociative('SELECT * FROM stock_movements WHERE product_id = ?', ['prod-1']);
        self::assertIsArray($movement);
        self::assertSame('SALE_OUT', $movement['movement_type']);
        self::assertEqualsWithDelta(-3.0, (float) $movement['qty_delta_base'], 0.001);
        self::assertEqualsWithDelta(3.0, (float) $movement['qty_original'], 0.001);
        self::assertSame('LEGACY_MOVIMIENTO_ITEM', $movement['source_type']);
        self::assertSame('sale-1:SALE_OUT:2', $movement['source_id']);
    }

    public function testPurchaseItemMovementIsMappedToPositiveInventoryV2Draft(): void
    {
        $this->port->applyMovementFromSaleItem(
            $this->sale(tipoId: 1),
            $this->item($this->product('prod-1'), 4.0),
            1,
        );

        self::assertEqualsWithDelta(4.0, (float) $this->conn->fetchOne('SELECT qty_on_hand FROM stock_items WHERE product_id = ?', ['prod-1']), 0.001);

        $movement = $this->conn->fetchAssociative('SELECT * FROM stock_movements WHERE product_id = ?', ['prod-1']);
        self::assertIsArray($movement);
        self::assertSame('PURCHASE_IN', $movement['movement_type']);
        self::assertEqualsWithDelta(4.0, (float) $movement['qty_delta_base'], 0.001);
        self::assertSame('sale-1:PURCHASE_IN:1', $movement['source_id']);
    }

    public function testDuplicateSaleItemMovementIsIdempotent(): void
    {
        $sale = $this->sale(tipoId: 2);
        $item = $this->item($this->product('prod-1'), 2.0);

        $this->port->applyMovementFromSaleItem($sale, $item, 1);
        $this->port->applyMovementFromSaleItem($sale, $item, 1);

        self::assertEqualsWithDelta(-2.0, (float) $this->conn->fetchOne('SELECT qty_on_hand FROM stock_items WHERE product_id = ?', ['prod-1']), 0.001);
        self::assertSame(1, (int) $this->conn->fetchOne('SELECT COUNT(*) FROM stock_movements WHERE product_id = ?', ['prod-1']));
    }

    private function product(string $id): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getInventoryBehavior')->willReturn(InventoryBehavior::TRACKED->value);

        return $product;
    }

    private function warehouse(string $id): Warehouse&MockObject
    {
        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn($id);

        return $warehouse;
    }

    private function sale(int $tipoId): SaleRecord&MockObject
    {
        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getId')->willReturn('sale-1');
        $sale->method('getTipoId')->willReturn($tipoId);
        $sale->method('getAlmacen')->willReturn($this->warehouse('wh-1'));
        $sale->method('getFecha')->willReturn(new \DateTimeImmutable('2026-04-29 10:00:00'));

        return $sale;
    }

    private function item(Product $product, float $qty): SaleItem&MockObject
    {
        $item = $this->createMock(SaleItem::class);
        $item->method('getProducto')->willReturn($product);
        $item->method('getCantidad')->willReturn($qty);

        return $item;
    }

    private function createSchema(): void
    {
        $this->conn->executeStatement(<<<'SQL'
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
SQL);

        $this->conn->executeStatement(<<<'SQL'
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
SQL);

        $this->conn->executeStatement(<<<'SQL'
CREATE TABLE stock_balances (
    warehouse_id VARCHAR(36) NOT NULL,
    product_id VARCHAR(36) NOT NULL,
    on_hand_qty DECIMAL(18,6) NOT NULL DEFAULT '0.000000',
    PRIMARY KEY (warehouse_id, product_id)
)
SQL);
    }
}
