<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Service;

use App\Inventory\Service\FifoKardexValuator;
use App\Inventory\Service\KardexCostSqlHelper;
use App\Inventory\Service\KardexSummaryService;
use App\Inventory\Service\WeightedAverageKardexValuator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class KardexSummaryServiceTest extends TestCase
{
    private Connection $connection;

    private KardexSummaryService $service;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE categoria (
                categoria_id TEXT NOT NULL PRIMARY KEY,
                descripcion TEXT NOT NULL
            )
        SQL);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE unidad (
                unidad_id INTEGER NOT NULL PRIMARY KEY,
                name TEXT NOT NULL
            )
        SQL);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE producto (
                producto_id TEXT NOT NULL PRIMARY KEY,
                categoria_id TEXT NOT NULL,
                unidad_id INTEGER NOT NULL,
                codigo TEXT,
                descripcion TEXT NOT NULL,
                precio_compra NUMERIC(10,2) DEFAULT NULL
            )
        SQL);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE goods_receipt_items (
                id INTEGER NOT NULL PRIMARY KEY,
                goods_receipt_id TEXT NOT NULL,
                line_no INTEGER NOT NULL,
                unit_cost NUMERIC(12,4) DEFAULT NULL
            )
        SQL);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE stock_lots (
                id TEXT NOT NULL PRIMARY KEY,
                product_id TEXT NOT NULL,
                warehouse_id TEXT NOT NULL,
                lot_number TEXT NOT NULL,
                qty_on_hand NUMERIC(18,6) NOT NULL DEFAULT 0,
                cost NUMERIC(12,4) DEFAULT NULL,
                received_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        SQL);

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE stock_movements (
                id TEXT NOT NULL PRIMARY KEY,
                warehouse_id TEXT NOT NULL,
                product_id TEXT NOT NULL,
                occurred_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                movement_type TEXT NOT NULL,
                qty_delta_base NUMERIC(18,6) NOT NULL,
                lot_id TEXT,
                source_type TEXT,
                source_id TEXT,
                note TEXT
            )
        SQL);

        $this->connection->executeStatement(
            "INSERT INTO categoria (categoria_id, descripcion) VALUES ('cat-1', 'Food')"
        );
        $this->connection->executeStatement(
            "INSERT INTO unidad (unidad_id, name) VALUES (1, 'bag')"
        );
        $this->connection->executeStatement(
            "INSERT INTO producto (producto_id, categoria_id, unidad_id, codigo, descripcion, precio_compra)
             VALUES ('p1', 'cat-1', 1, 'SKU-1', 'Cafe molido', 12.76)"
        );
        $this->connection->executeStatement(
            "INSERT INTO stock_lots (id, product_id, warehouse_id, lot_number, qty_on_hand, cost, received_at, created_at)
             VALUES
                ('lot-1', 'p1', 'wh1', 'L-001', 100, 10.00, '2026-04-01 10:00:00', '2026-04-01 10:00:00'),
                ('lot-2', 'p1', 'wh1', 'L-002', 9, 15.00, '2026-06-01 10:00:00', '2026-06-01 10:00:00')"
        );
        $this->connection->executeStatement(
            "INSERT INTO stock_movements (id, warehouse_id, product_id, occurred_at, created_at, movement_type, qty_delta_base, lot_id)
             VALUES
                ('m1', 'wh1', 'p1', '2026-04-01 10:00:00', '2026-04-01 10:00:00', 'PURCHASE_IN', 100, 'lot-1'),
                ('m2', 'wh1', 'p1', '2026-05-01 10:00:00', '2026-05-01 10:00:00', 'SALE_OUT', -30, NULL),
                ('m3', 'wh1', 'p1', '2026-06-01 10:00:00', '2026-06-01 10:00:00', 'PURCHASE_IN', 9, 'lot-2')"
        );

        $this->service = new KardexSummaryService(
            $this->connection,
            new FifoKardexValuator(),
            new WeightedAverageKardexValuator(),
            new KardexCostSqlHelper($this->connection),
        );
    }

    public function testSummaryUsesPurchasePriceValuation(): void
    {
        $summary = $this->service->getSummary('p1', 'wh1');

        self::assertSame('weighted_purchase_price', $summary['valuationMethod']);
        self::assertSame('SKU-1', $summary['product']['code']);
        self::assertSame('Cafe molido', $summary['product']['name']);
        self::assertSame('10.5696', $summary['avgUnitCost']);
        self::assertSame('79.000000', $summary['currentStock']);
        self::assertSame('835.00', $summary['stockValue']);
        self::assertSame('109.000000', $summary['totalIn']);
        self::assertSame('30.000000', $summary['totalOut']);
        self::assertSame(3, $summary['movementCount']);
        self::assertSame('2026-04-01', $summary['periodFrom']);
        self::assertSame('2026-06-01', $summary['periodTo']);
    }

    public function testSummaryUsesFifoValuation(): void
    {
        $summary = $this->service->getSummary('p1', 'wh1', valuation: 'fifo');

        self::assertSame('fifo', $summary['valuationMethod']);
        self::assertSame('79.000000', $summary['currentStock']);
        self::assertSame('835.00', $summary['stockValue']);
        self::assertSame('10.5696', $summary['avgUnitCost']);
    }
}