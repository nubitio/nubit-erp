<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Financial\Application\Query;

use App\Reporting\Financial\Application\Query\ProfitAndLossQuery;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class ProfitAndLossQueryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $pdo = $this->connection->getNativeConnection();
        static::assertInstanceOf(\PDO::class, $pdo);
        $pdo->sqliteCreateFunction('DATE_FORMAT', static function (?string $date, string $format): ?string {
            if (null === $date) {
                return null;
            }
            $phpFormat = str_replace(['%Y', '%m', '%d'], ['Y', 'm', 'd'], $format);
            $ts = strtotime($date);

            return false !== $ts ? date($phpFormat, $ts) : null;
        });

        $this->connection->executeStatement('
            CREATE TABLE tax_document (
                tax_document_id VARCHAR(36) NOT NULL PRIMARY KEY,
                document_type VARCHAR(10) NOT NULL,
                issue_date DATE NOT NULL,
                taxable_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                void_ticket VARCHAR(255) DEFAULT NULL,
                void_correlation VARCHAR(255) DEFAULT NULL
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE sales (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                status VARCHAR(30) NOT NULL DEFAULT \'COMPLETED\',
                issued_at DATETIME NOT NULL
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE sale_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sale_id VARCHAR(36) NOT NULL,
                qty DECIMAL(14,3) NOT NULL,
                unit_cost_snapshot DECIMAL(12,4) DEFAULT NULL
            )
        ');
        $this->connection->executeStatement(
            'CREATE TABLE returns (id VARCHAR(36) PRIMARY KEY, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE return_items (id INTEGER PRIMARY KEY AUTOINCREMENT, return_id VARCHAR(36) NOT NULL, sale_item_id INTEGER, qty_returned DECIMAL(14,3) NOT NULL)',
        );

        $this->connection->executeStatement('
            CREATE TABLE expense_category (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE supplier_bill (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                category_id INTEGER NOT NULL,
                issue_date DATE NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                purchase_id VARCHAR(36) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'PENDING\'
            )
        ');
        $this->connection->executeStatement(
            'CREATE TABLE expense_report (id VARCHAR(36) PRIMARY KEY, category_id INTEGER NOT NULL, amount DECIMAL(12,2) NOT NULL, expense_date DATE NOT NULL, status VARCHAR(20) NOT NULL)',
        );
    }

    private function seedJuneFixtures(): void
    {
        $this->connection->insert('tax_document', [
            'tax_document_id' => 'd1',
            'document_type' => '01',
            'issue_date' => '2026-06-05',
            'taxable_amount' => 1000,
        ]);
        $this->connection->insert('tax_document', [
            'tax_document_id' => 'd2',
            'document_type' => '03',
            'issue_date' => '2026-06-10',
            'taxable_amount' => 500,
        ]);
        $this->connection->insert('tax_document', [
            'tax_document_id' => 'd3',
            'document_type' => '07',
            'issue_date' => '2026-06-12',
            'taxable_amount' => 100,
        ]);
        $this->connection->insert('tax_document', [
            'tax_document_id' => 'd5',
            'document_type' => '08',
            'issue_date' => '2026-06-13',
            'taxable_amount' => 25,
        ]);
        // Voided invoice — must not count toward revenue.
        $this->connection->insert('tax_document', [
            'tax_document_id' => 'd4',
            'document_type' => '01',
            'issue_date' => '2026-06-15',
            'taxable_amount' => 9999,
            'void_ticket' => 'T1',
        ]);

        $this->connection->insert('sales', [
            'id' => 's1',
            'status' => 'COMPLETED',
            'issued_at' => '2026-06-05 10:00:00',
        ]);
        $this->connection->insert('sale_items', [
            'id' => 1,
            'sale_id' => 's1',
            'qty' => 2,
            'unit_cost_snapshot' => 100,
        ]);
        $this->connection->insert('sale_items', ['sale_id' => 's1', 'qty' => 1, 'unit_cost_snapshot' => 300]);
        $this->connection->insert('returns', [
            'id' => 'r1',
            'status' => 'COMPLETED',
            'created_at' => '2026-06-18 12:00:00',
        ]);
        $this->connection->insert('return_items', ['return_id' => 'r1', 'sale_item_id' => 1, 'qty_returned' => 1]);
        // Voided sale — its cost must not count.
        $this->connection->insert('sales', ['id' => 's2', 'status' => 'VOIDED', 'issued_at' => '2026-06-06 10:00:00']);
        $this->connection->insert('sale_items', [
            'id' => 3,
            'sale_id' => 's2',
            'qty' => 5,
            'unit_cost_snapshot' => 1000,
        ]);
        $this->connection->insert('returns', [
            'id' => 'r2',
            'status' => 'COMPLETED',
            'created_at' => '2026-06-19 12:00:00',
        ]);
        $this->connection->insert('return_items', ['return_id' => 'r2', 'sale_item_id' => 3, 'qty_returned' => 1]);

        $this->connection->insert('expense_category', ['id' => 1, 'name' => 'Alquiler']);
        $this->connection->insert('expense_category', ['id' => 2, 'name' => 'Servicios']);
        $this->connection->insert('supplier_bill', [
            'id' => 'b1',
            'category_id' => 1,
            'issue_date' => '2026-06-01',
            'amount' => 200,
            'status' => 'PAID',
        ]);
        $this->connection->insert('supplier_bill', [
            'id' => 'b2',
            'category_id' => 2,
            'issue_date' => '2026-06-20',
            'amount' => 50,
            'status' => 'PENDING',
        ]);
        // Inventory purchase — represented in profit through COGS, not OPEX.
        $this->connection->insert('supplier_bill', [
            'id' => 'b4',
            'category_id' => 2,
            'issue_date' => '2026-06-20',
            'amount' => 800,
            'purchase_id' => 'purchase-1',
            'status' => 'PENDING',
        ]);
        $this->connection->insert('expense_report', [
            'id' => 'e1',
            'category_id' => 2,
            'amount' => 30,
            'expense_date' => '2026-06-21',
            'status' => 'APPROVED',
        ]);
        $this->connection->insert('expense_report', [
            'id' => 'e2',
            'category_id' => 2,
            'amount' => 999,
            'expense_date' => '2026-06-21',
            'status' => 'REJECTED',
        ]);
        // Voided bill — must not count.
        $this->connection->insert('supplier_bill', [
            'id' => 'b3',
            'category_id' => 1,
            'issue_date' => '2026-06-10',
            'amount' => 9999,
            'status' => 'VOID',
        ]);
    }

    public function testComputeAggregatesRevenueCogsAndOpexIntoNetResult(): void
    {
        $this->seedJuneFixtures();

        $query = new ProfitAndLossQuery($this->connection);
        $result = $query->compute('202606');

        static::assertSame(1525.0, $result['ingresos']['bruto']);
        static::assertSame(100.0, $result['ingresos']['notasCredito']);
        static::assertSame(1425.0, $result['ingresos']['neto']);

        // 2*100 + 1*300 - returned 1*100 = 400 (voided sale excluded).
        static::assertSame(400.0, $result['costoVentas']['monto']);
        static::assertTrue($result['costoVentas']['isEstimate']);

        static::assertSame(1025.0, $result['utilidadBruta']);

        static::assertSame(280.0, $result['gastosOperativos']['total']); // 200 + 50 + 30; inventory bill excluded.
        static::assertCount(2, $result['gastosOperativos']['porCategoria']);

        static::assertSame(745.0, $result['utilidadNeta']);

        static::assertSame('Junio 2026', $result['periodLabel']);
        static::assertCount(6, $result['historial']);
        static::assertSame('202606', $result['historial'][0]['periodo']);
    }

    public function testComputeDefaultsToCurrentMonthWhenNoPeriodGiven(): void
    {
        $query = new ProfitAndLossQuery($this->connection);
        $result = $query->compute();

        static::assertSame(date('Ym'), $result['period']);
    }

    public function testEmptyPeriodReturnsZeroedResult(): void
    {
        $query = new ProfitAndLossQuery($this->connection);
        $result = $query->compute('202601');

        static::assertSame(0.0, $result['ingresos']['neto']);
        static::assertSame(0.0, $result['costoVentas']['monto']);
        static::assertSame(0.0, $result['gastosOperativos']['total']);
        static::assertSame(0.0, $result['utilidadNeta']);
    }
}
