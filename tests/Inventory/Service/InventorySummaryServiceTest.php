<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Service;

use App\Inventory\Service\InventorySummaryService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class InventorySummaryServiceTest extends TestCase
{
    private Connection $connection;

    private InventorySummaryService $service;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement(<<<'SQL'
                CREATE TABLE producto (
                    producto_id TEXT NOT NULL PRIMARY KEY,
                    categoria_id TEXT NOT NULL,
                    precio_compra NUMERIC(10,2) DEFAULT NULL,
                    stock_minimo NUMERIC(12,3) DEFAULT NULL
                )
            SQL);

        $this->connection->executeStatement(<<<'SQL'
                CREATE TABLE stock_items (
                    warehouse_id TEXT NOT NULL,
                    product_id TEXT NOT NULL,
                    qty_on_hand NUMERIC(18,6) NOT NULL DEFAULT 0,
                    PRIMARY KEY (warehouse_id, product_id)
                )
            SQL);

        $this->connection->executeStatement("INSERT INTO producto (producto_id, categoria_id, precio_compra, stock_minimo) VALUES
                ('p1', 'cat-office', 10.00, 50),
                ('p2', 'cat-office', 5.50, 20),
                ('p3', 'cat-grocery', 8.00, 10)");

        $this->connection->executeStatement("INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand) VALUES
                ('wh1', 'p1', 100),
                ('wh1', 'p2', 5),
                ('wh2', 'p3', 0)");

        $this->service = new InventorySummaryService($this->connection);
    }

    public function testSummaryAcrossAllWarehouses(): void
    {
        $summary = $this->service->getSummary(null);

        self::assertSame(3, $summary['totalSkus']);
        self::assertSame(2, $summary['categoryCount']);
        self::assertSame('1027.50', $summary['stockValueAtCost']);
        self::assertSame(2, $summary['lowStockCount']);
        self::assertSame(1, $summary['outOfStockCount']);
        self::assertNull($summary['warehouseId']);
    }

    public function testSummaryFilteredByWarehouse(): void
    {
        $summary = $this->service->getSummary('wh1');

        self::assertSame(2, $summary['totalSkus']);
        self::assertSame(1, $summary['categoryCount']);
        self::assertSame('1027.50', $summary['stockValueAtCost']);
        self::assertSame(1, $summary['lowStockCount']);
        self::assertSame(0, $summary['outOfStockCount']);
        self::assertSame('wh1', $summary['warehouseId']);
    }
}
