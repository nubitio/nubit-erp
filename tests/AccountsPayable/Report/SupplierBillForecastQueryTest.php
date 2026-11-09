<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Report;

use App\AccountsPayable\Controller\SupplierBillForecastController;
use App\AccountsPayable\Report\SupplierBillForecastQuery;
use App\AccountsPayable\Report\SupplierBillSummaryQuery;
use App\Identity\Auth\BranchContextServiceInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SupplierBillForecastQueryTest extends TestCase
{
    public function testProjectsCashReceiptsAndPayablesForTheBranchInPen(): void
    {
        $db = $this->database();
        $today = new DateTimeImmutable('2026-11-07');

        self::assertSame(
            [
                'currency' => 'PEN',
                // open session: 200 opening + 100 sales + 20 cash in - 30 cash out - 10 refund = 280; closed and other-branch sessions ignored
                'cashBalance' => '280.00',
                // only PEN, not voided, due today..+30d; the 2026-12-07 one is outside the horizon
                'expectedReceipts' => '150.00',
                'payables' => '60.20',
                'projectedBalance' => '369.80',
                'overdueReceivables' => '75.00',
                'overdueReceivablesCount' => 1,
            ],
            $this->query($db)->execute('a', $today),
        );
    }

    public function testBranchWithNoActivityProjectsZero(): void
    {
        $result = $this->query($this->database())->execute('empty', new DateTimeImmutable('2026-11-07'));

        self::assertSame('0.00', $result['cashBalance']);
        self::assertSame('0.00', $result['projectedBalance']);
        self::assertSame(0, $result['overdueReceivablesCount']);
    }

    public function testMissingBranchIsNotReportedAsAnEmptyForecast(): void
    {
        $branch = $this->createMock(BranchContextServiceInterface::class);
        $branch->expects(self::once())->method('getCurrentBranchId')->willReturn(null);
        $controller = new SupplierBillForecastController($this->query($this->database()), $branch);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Selecciona una sucursal');
        $controller();
    }

    private function query(Connection $db): SupplierBillForecastQuery
    {
        return new SupplierBillForecastQuery($db, new SupplierBillSummaryQuery($db));
    }

    private function database(): Connection
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement(
            'CREATE TABLE cash_sessions (id INTEGER, branch_id TEXT, status TEXT, opening_amount DECIMAL(12,2))',
        );
        $db->executeStatement(
            'CREATE TABLE cash_movements (cash_session_id INTEGER, movement_type TEXT, amount DECIMAL(12,2))',
        );
        $db->executeStatement(
            'CREATE TABLE sales (branch_id TEXT, currency TEXT, balance_due DECIMAL(12,2), status TEXT, due_date TEXT)',
        );
        $db->executeStatement(
            'CREATE TABLE supplier_bill (branch_id TEXT, supplier_id TEXT, currency TEXT, balance_due DECIMAL(12,2), status TEXT, due_date TEXT)',
        );

        foreach ([
            [1, 'a', 'OPEN',   '200.00'],
            [2, 'a', 'CLOSED', '999.00'],
            [3, 'b', 'OPEN',   '555.00'],
        ] as $row) {
            $db->executeStatement('INSERT INTO cash_sessions VALUES (?, ?, ?, ?)', $row);
        }
        foreach ([
            [1, 'SALE_IN',    '100.00'],
            [1, 'CASH_IN',    '20.00'],
            [1, 'CASH_OUT',   '30.00'],
            [1, 'REFUND_OUT', '10.00'],
            [2, 'SALE_IN',    '50.00'],
            [3, 'SALE_IN',    '70.00'],
        ] as $row) {
            $db->executeStatement('INSERT INTO cash_movements VALUES (?, ?, ?)', $row);
        }
        foreach ([
            ['a', 'PEN', '100.00', 'COMPLETED', '2026-11-07'], // due today: expected
            ['a', 'PEN', '50.00',  'COMPLETED', '2026-12-07'], // +30 days: expected (inclusive)
            ['a', 'PEN', '40.00',  'COMPLETED', '2026-12-08'], // beyond the horizon
            ['a', 'PEN', '75.00',  'COMPLETED', '2026-11-01'], // overdue
            ['a', 'PEN', '500.00', 'VOIDED',    '2026-11-10'], // voided
            ['a', 'USD', '300.00', 'COMPLETED', '2026-11-10'], // other currency
            ['b', 'PEN', '900.00', 'COMPLETED', '2026-11-10'], // other branch
        ] as $row) {
            $db->executeStatement('INSERT INTO sales VALUES (?, ?, ?, ?, ?)', $row);
        }
        foreach ([
            ['a', 'one', 'PEN', '10.20', 'PENDING', '2026-11-10'],
            ['a', 'two', 'PEN', '50.00', 'PENDING', '2026-11-30'],
            ['a', 'two', 'USD', '70.00', 'PENDING', '2026-11-30'],
        ] as $row) {
            $db->executeStatement('INSERT INTO supplier_bill VALUES (?, ?, ?, ?, ?, ?)', $row);
        }

        return $db;
    }
}
