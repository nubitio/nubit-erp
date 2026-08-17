<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Financial\Application\Query;

use App\Reporting\Financial\Application\Query\CashFlowQuery;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class CashFlowQueryTest extends TestCase
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
            $phpFormat = str_replace(['%Y', '%m', '%d', '%x', '%v'], ['Y', 'm', 'd', 'o', 'W'], $format);
            $ts = strtotime($date);

            return false !== $ts ? date($phpFormat, $ts) : null;
        });

        $this->connection->executeStatement('
            CREATE TABLE sale_payments (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                amount DECIMAL(12,2) NOT NULL,
                paid_at DATETIME DEFAULT NULL
            )
        ');
        $this->connection->executeStatement('
            CREATE TABLE supplier_payment (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                amount DECIMAL(12,2) NOT NULL,
                paid_at DATE NOT NULL
            )
        ');
        $this->connection->executeStatement('
            CREATE TABLE cash_movement (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type VARCHAR(20) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                created_at DATETIME NOT NULL
            )
        ');
        $this->connection->executeStatement('
            CREATE TABLE sales (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                status VARCHAR(30) NOT NULL DEFAULT \'COMPLETED\',
                due_date DATE DEFAULT NULL,
                balance_due DECIMAL(12,2) NOT NULL DEFAULT 0
            )
        ');
        $this->connection->executeStatement('
            CREATE TABLE supplier_bill (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                due_date DATE NOT NULL,
                balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT \'PENDING\'
            )
        ');
    }

    public function testComputeAggregatesActualCashFlowByIsoWeek(): void
    {
        $this->connection->insert('sale_payments', ['id' => 'p1', 'amount' => 300, 'paid_at' => '2026-06-03 10:00:00']);
        $this->connection->insert('sale_payments', ['id' => 'p2', 'amount' => 200, 'paid_at' => '2026-06-04 10:00:00']);
        // Unpaid — must be excluded.
        $this->connection->insert('sale_payments', ['id' => 'p3', 'amount' => 999, 'paid_at' => null]);

        $this->connection->insert('supplier_payment', ['id' => 'sp1', 'amount' => 150, 'paid_at' => '2026-06-03']);

        $this->connection->insert('cash_movement', [
            'type' => 'income',
            'amount' => 50,
            'created_at' => '2026-06-04 08:00:00',
        ]);
        $this->connection->insert('cash_movement', [
            'type' => 'expense',
            'amount' => 20,
            'created_at' => '2026-06-04 09:00:00',
        ]);

        $query = new CashFlowQuery($this->connection);
        $result = $query->compute('2026-06-01', '2026-06-30');

        static::assertCount(1, $result['real']);
        $week = $result['real'][0];
        static::assertSame(550.0, $week['entradas']); // 300 + 200 + 50
        static::assertSame(170.0, $week['salidas']); // 150 + 20
        static::assertSame(380.0, $week['neto']);
    }

    public function testComputeProyectadoAggregatesArAndApWithinNext30Days(): void
    {
        $inFifteenDays = new DateTimeImmutable('today')
            ->modify('+15 days')
            ->format('Y-m-d');

        $this->connection->insert('sales', [
            'id' => 's1',
            'status' => 'COMPLETED',
            'due_date' => $inFifteenDays,
            'balance_due' => 400,
        ]);
        // Already paid — balance_due 0, must be excluded.
        $this->connection->insert('sales', [
            'id' => 's2',
            'status' => 'COMPLETED',
            'due_date' => $inFifteenDays,
            'balance_due' => 0,
        ]);
        // Voided — must be excluded.
        $this->connection->insert('sales', [
            'id' => 's3',
            'status' => 'VOIDED',
            'due_date' => $inFifteenDays,
            'balance_due' => 999,
        ]);

        $this->connection->insert('supplier_bill', [
            'id' => 'b1',
            'due_date' => $inFifteenDays,
            'balance_due' => 100,
            'status' => 'PENDING',
        ]);
        $this->connection->insert('supplier_bill', [
            'id' => 'b2',
            'due_date' => $inFifteenDays,
            'balance_due' => 999,
            'status' => 'VOID',
        ]);

        $query = new CashFlowQuery($this->connection);
        $result = $query->compute();

        static::assertCount(1, $result['proyectado']);
        $week = $result['proyectado'][0];
        static::assertSame(400.0, $week['cobrosEsperados']);
        static::assertSame(100.0, $week['pagosEsperados']);
        static::assertSame(300.0, $week['netoEsperado']);
    }

    public function testComputeDefaultsToCurrentMonthRange(): void
    {
        $query = new CashFlowQuery($this->connection);
        $result = $query->compute();

        static::assertSame(date('Y-m-01'), $result['from']);
        static::assertSame(date('Y-m-d'), $result['to']);
    }
}
