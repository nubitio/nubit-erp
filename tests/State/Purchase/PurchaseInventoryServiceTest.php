<?php

declare(strict_types=1);

namespace App\Tests\State\Purchase;

use App\Catalog\Entity\Product;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Core\Contract\StockValidationPolicyInterface;
use App\Inventory\Core\Dto\StockValidationResult;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Core\Service\UnitConversionService;
use App\State\Purchase\PurchaseInventoryService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class PurchaseInventoryServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testRecordPurchaseReceiptWritesInventoryMovementForTrackedItems(): void
    {
        $service = new PurchaseInventoryService($this->newInventoryService());

        $purchase = (new Purchase())
            ->setId('purchase-1')
            ->setWarehouse($this->newWarehouse('wh-1'))
            ->setIssuedAt(new \DateTimeImmutable('2026-04-26 10:30:00'));

        $purchase->addItem(
            (new PurchaseItem())
                ->setProduct($this->newProduct('prod-1'))
                ->setQty('5.000')
        );

        $service->recordPurchaseReceipt($purchase);

        self::assertEqualsWithDelta(
            5.0,
            (float) $this->connection->fetchOne(
                'SELECT qty_on_hand FROM stock_items WHERE warehouse_id = :warehouse_id AND product_id = :product_id',
                ['warehouse_id' => 'wh-1', 'product_id' => 'prod-1']
            ),
            0.001
        );

        $movement = $this->connection->fetchAssociative(
            'SELECT movement_type, qty_delta_base, qty_original, unit_id, source_type, source_id, occurred_at FROM stock_movements LIMIT 1'
        );

        self::assertIsArray($movement);
        self::assertSame(StockMovementType::PURCHASE->value, $movement['movement_type']);
        self::assertEqualsWithDelta(5.0, (float) $movement['qty_delta_base'], 0.001);
        self::assertEqualsWithDelta(5.0, (float) $movement['qty_original'], 0.001);
        self::assertNull($movement['unit_id']);
        self::assertSame('PURCHASE_ITEM', $movement['source_type']);
        self::assertSame('purchase-1:1', $movement['source_id']);
        self::assertSame('2026-04-26 10:30:00', $movement['occurred_at']);
    }

    public function testRecordPurchaseReceiptSkipsUntrackedProducts(): void
    {
        $service = new PurchaseInventoryService($this->newInventoryService());

        $purchase = (new Purchase())
            ->setId('purchase-1')
            ->setWarehouse($this->newWarehouse('wh-1'));

        $purchase->addItem(
            (new PurchaseItem())
                ->setProduct($this->newServiceProduct('svc-1'))
                ->setQty('5.000')
        );

        $service->recordPurchaseReceipt($purchase);

        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM stock_movements'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM stock_items'));
    }

    public function testRecordPurchaseReceiptSkipsWhenWarehouseIsMissing(): void
    {
        $service = new PurchaseInventoryService($this->newInventoryService());

        $purchase = (new Purchase())->setId('purchase-1');
        $purchase->addItem(
            (new PurchaseItem())
                ->setProduct($this->newProduct('prod-1'))
                ->setQty('5.000')
        );

        $service->recordPurchaseReceipt($purchase);

        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM stock_movements'));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM stock_items'));
    }

    private function newInventoryService(): InventoryService
    {
        return new InventoryService(
            $this->connection,
            new class () implements StockValidationPolicyInterface {
                public function validateSale(
                    Product $product,
                    string $warehouseId,
                    string $requestedQtyBase,
                    string $onHandQtyBase,
                ): StockValidationResult {
                    return StockValidationResult::allowed();
                }
            },
            new UnitConversionService($this->connection),
            new class () implements EventDispatcherInterface {
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
        );
    }

    private function createSchema(): void
    {
        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE stock_movements (
    id TEXT PRIMARY KEY,
    warehouse_id TEXT NOT NULL,
    product_id TEXT NOT NULL,
    occurred_at TEXT NOT NULL,
    movement_type TEXT NOT NULL,
    quantity_delta NUMERIC NOT NULL,
    qty_delta_base NUMERIC NOT NULL,
    qty_original NUMERIC NOT NULL,
    unit_id INTEGER DEFAULT NULL,
    source_type TEXT NOT NULL,
    source_id TEXT DEFAULT NULL,
    note TEXT DEFAULT NULL,
    idempotency_key TEXT DEFAULT NULL UNIQUE,
    sync_status TEXT NOT NULL,
    synced_at TEXT DEFAULT NULL,
    version_no INTEGER NOT NULL,
    last_modified_at TEXT NOT NULL,
    created_at TEXT NOT NULL
)
SQL);

        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE stock_items (
    warehouse_id TEXT NOT NULL,
    product_id TEXT NOT NULL,
    qty_on_hand NUMERIC NOT NULL DEFAULT 0,
    qty_reserved NUMERIC NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL,
    last_movement_id TEXT DEFAULT NULL,
    version INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (warehouse_id, product_id)
)
SQL);
    }

    private function newProduct(string $id): Product
    {
        $product = new Product();
        $this->setEntityId($product, $id);
        $product->setType(1);

        return $product;
    }

    private function newServiceProduct(string $id): Product
    {
        $product = new Product();
        $this->setEntityId($product, $id);
        $product->setType(2);

        return $product;
    }

    private function newWarehouse(string $id): Warehouse
    {
        $warehouse = new Warehouse();
        $this->setEntityId($warehouse, $id);

        return $warehouse;
    }

    private function setEntityId(object $entity, int|string $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }
}
