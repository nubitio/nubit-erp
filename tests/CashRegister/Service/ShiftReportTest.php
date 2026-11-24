<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Service;

use App\CashRegister\Service\SessionSalesBreakdown;
use App\CashRegister\Service\ShiftCash;
use App\CashRegister\Service\ShiftReceipts;
use App\CashRegister\Service\ShiftReport;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShiftReportTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach ([
            'CREATE TABLE cash_sessions (id INTEGER, status TEXT, opening_amount NUMERIC, expected_amount NUMERIC, counted_amount NUMERIC,
                difference_amount NUMERIC, close_note TEXT, opened_at TEXT, closed_at TEXT, counted_denominations TEXT,
                opening_fund_kept INTEGER, cash_withdrawn_at_close NUMERIC, branch_id TEXT, cash_register_id INTEGER, opened_by INTEGER, closed_by INTEGER)',
            'CREATE TABLE cash_register (id INTEGER, name TEXT)',
            'CREATE TABLE usuario (usuario_id INTEGER, username TEXT)',
            'CREATE TABLE empresa (razon_social TEXT, ruc TEXT)',
            'CREATE TABLE local (local_id TEXT, descripcion TEXT)',
            'CREATE TABLE payment_method (id INTEGER, name TEXT)',
            'CREATE TABLE sales (id TEXT, cash_session_id INTEGER, grand_total NUMERIC, status TEXT, deleted_at TEXT, receivable_account INTEGER)',
            'CREATE TABLE sale_payments (id INTEGER PRIMARY KEY, sale_id TEXT, payment_method_id INTEGER, amount NUMERIC, idempotency_key TEXT)',
            'CREATE TABLE tax_document (sale_id TEXT, document_type TEXT, series TEXT, number TEXT, taxable_amount NUMERIC, igv_amount NUMERIC, total NUMERIC)',
            'CREATE TABLE cash_movement_category (id INTEGER, code TEXT, name TEXT)',
            'CREATE TABLE cash_movements (cash_session_id INTEGER, category_id INTEGER, movement_type TEXT, amount NUMERIC, description TEXT, created_at TEXT, id INTEGER PRIMARY KEY)',
        ] as $sql) {
            $this->db->executeStatement($sql);
        }
    }

    private function seedClosedShift(): void
    {
        $db = $this->db;
        $db->executeStatement("INSERT INTO empresa VALUES ('Comercial Andina S.A.C.', '20512345678')");
        $db->executeStatement("INSERT INTO local VALUES ('b1', 'Sucursal Principal')");
        $db->executeStatement("INSERT INTO usuario VALUES (1, 'mario'), (2, 'elena')");
        $db->executeStatement("INSERT INTO cash_register VALUES (3, 'Caja 03')");
        $db->executeStatement("INSERT INTO payment_method VALUES (1, 'Efectivo'), (4, 'Tarjeta')");
        $db->executeStatement(
            "INSERT INTO cash_sessions VALUES (78, 'CLOSED', 200, 1124.40, 1124.40, 0, NULL, '2026-11-22 08:00:00',
            '2026-11-22 19:34:00', '{\"20000\":2,\"10000\":3}', 1, 924.40, 'b1', 3, 1, 2)",
        );
        // Sales: two boletas (one in cash, one by card) and a factura; one credit note against a boleta; a voided sale.
        $db->executeStatement("INSERT INTO sales VALUES ('s1', 78, 118, 'COMPLETED', NULL, 0), ('s2', 78, 200, 'COMPLETED', NULL, 0),
            ('s3', 78, 59, 'COMPLETED', NULL, 0), ('s4', 78, 999, 'VOIDED', NULL, 0)");
        $db->executeStatement("INSERT INTO sale_payments (sale_id, payment_method_id, amount, idempotency_key)
            VALUES ('s1', 1, 118, 'a'), ('s2', 4, 200, 'b'), ('s3', 1, 59, 'c')");
        $db->executeStatement("INSERT INTO tax_document VALUES
            ('s1', '03', 'B003', '00002101', 100, 18, 118), ('s3', '03', 'B003', '00002158', 50, 9, 59),
            ('s2', '01', 'F003', '00000412', 169.49, 30.51, 200), ('s1', '07', 'BC03', '00000007', 10, 1.8, 11.8),
            ('s4', '03', 'B003', '00009999', 1, 0.18, 1.18)");
        $db->executeStatement(
            "INSERT INTO cash_movement_category VALUES (1, 'petty_cash_expense', 'Gasto de caja chica'), (2, 'manager_withdrawal', 'Retiro de efectivo')",
        );
        $db->executeStatement("INSERT INTO cash_movements (cash_session_id, category_id, movement_type, amount, description, created_at) VALUES
            (78, NULL, 'SALE_IN', 177, NULL, '2026-11-22 10:00:00'), (78, 1, 'CASH_OUT', 62, 'Pago de movilidad', '2026-11-22 10:05:00'),
            (78, 2, 'CASH_OUT', 1000, 'Retiro parcial', '2026-11-22 15:10:00')");
    }

    private function report(): ShiftReport
    {
        return new ShiftReport(
            $this->db,
            new SessionSalesBreakdown($this->db),
            new ShiftReceipts($this->db),
            new ShiftCash($this->db),
        );
    }

    public function testUnknownShiftIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->report()->forSession(1);
    }

    public function testReceiptsAreGroupedByTypeWithTheirRangeAndCreditNotesSubtract(): void
    {
        $this->seedClosedShift();

        $documents = $this->report()->forSession(78)['documents'];

        self::assertSame(['Boletas', 'Facturas', 'N. de crédito'], array_column($documents['rows'], 'label'));
        self::assertSame(['B003-00002101 a 00002158'], $documents['rows'][0]['ranges']);
        self::assertSame(['BC03-00000007'], $documents['rows'][2]['ranges'], 'one document is its own range');
        self::assertSame('-11.80', $documents['rows'][2]['total']);
        self::assertSame(4, $documents['count'], 'the voided sale is left out');
        self::assertSame('365.20', $documents['total'], '118 + 59 + 200 - 11.80');
    }

    public function testSalesByMethodAndTheCashOfTheDrawer(): void
    {
        $this->seedClosedShift();

        $report = $this->report()->forSession(78);

        self::assertSame('Comercial Andina S.A.C.', $report['company']['name']);
        self::assertSame('Sucursal Principal', $report['company']['branch']);
        self::assertSame('Caja 03', $report['shift']['registerName']);
        self::assertSame('closed', $report['shift']['status']);
        self::assertSame('elena', $report['shift']['closedBy'], 'somebody other than the cashier closed it');
        self::assertSame(['Efectivo', 'Tarjeta'], array_column($report['sales']['byMethod'], 'name'));
        self::assertSame('46.9', $report['sales']['byMethod'][0]['percent']);

        $cash = $report['cash'];
        self::assertSame('200.00', $cash['opening']);
        self::assertSame('177.00', $cash['cashSales']);
        self::assertSame('62.00', $cash['expenses']);
        self::assertSame('1000.00', $cash['withdrawals']);
        self::assertSame('1124.40', $cash['expected'], 'what the closing stored wins over a recomputation');
        self::assertSame('0.00', $cash['difference']);
        self::assertSame(['20000' => 2, '10000' => 3], $cash['denominations']);
        self::assertTrue($cash['fundKept']);
        self::assertSame('924.40', $cash['withdrawnAtClose']);
    }

    public function testOutflowsAreListedOldestFirstSplitIntoExpensesAndWithdrawals(): void
    {
        $this->seedClosedShift();

        $outflows = $this->report()->forSession(78)['outflows'];

        self::assertSame(['expense', 'withdrawal'], array_column($outflows, 'kind'));
        self::assertSame(['Pago de movilidad', 'Retiro parcial'], array_column($outflows, 'description'));
        self::assertSame(['62.00', '1000.00'], array_column($outflows, 'amount'));
    }

    public function testAShiftClosedBeforeTheDetailWasRecordedHasNoneOfIt(): void
    {
        $this->seedClosedShift();
        $this->db->executeStatement(
            'UPDATE cash_sessions SET counted_denominations = NULL, opening_fund_kept = NULL, cash_withdrawn_at_close = NULL, closed_by = 1',
        );

        $cash = $this->report()->forSession(78)['cash'];

        self::assertNull($cash['denominations']);
        self::assertNull($cash['fundKept']);
        self::assertNull($cash['withdrawnAtClose']);
        self::assertNull($this->report()->forSession(78)['shift']['closedBy']);
    }
}
