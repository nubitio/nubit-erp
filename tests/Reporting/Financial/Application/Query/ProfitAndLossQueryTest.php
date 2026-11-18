<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Financial\Application\Query;

use App\Reporting\Financial\Application\Query\ProfitAndLossQuery;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Settings\Entity\TenantCapability;
use App\Settings\Repository\TenantCapabilityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
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

    public function testWithoutTheLedgerTheResultIsLabelledAnEstimate(): void
    {
        $result = new ProfitAndLossQuery($this->connection)->compute('202606');

        static::assertSame('estimate', $result['source']);
    }

    public function testWithTheLedgerOnTheResultComesFromJournalEntriesAndIsNotAnEstimate(): void
    {
        $this->seedJuneFixtures(); // operational data that must be ignored now
        $this->createLedger();
        $this->account('a-sales', '70111', 'Ventas', 'INCOME');
        $this->account('a-cogs', '691', 'Costo de ventas', 'EXPENSE');
        $this->account('a-rent', '6351', 'Alquileres', 'EXPENSE');
        $this->account('a-bank', '1041', 'Banco', 'ASSET');
        $this->entry('e1', '2026-06-10', [['a-bank', 1180, 0], ['a-sales', 0, 1180]]);
        $this->entry('e2', '2026-06-12', [['a-sales', 100, 0], ['a-bank', 0, 100]]); // credit note
        $this->entry('e3', '2026-06-15', [['a-cogs', 400, 0], ['a-bank', 0, 400]]);
        $this->entry('e4', '2026-06-20', [['a-rent', 300, 0], ['a-bank', 0, 300]]);
        $this->entry('e5', '2026-05-20', [['a-rent', 999, 0], ['a-bank', 0, 999]]); // another month

        $repository = $this->createMock(TenantCapabilityRepository::class);
        $repository
            ->method('allByKey')
            ->willReturn([
                'accounting.general_ledger' => new TenantCapability('accounting.general_ledger')->setEnabled(true),
            ]);
        $features = $this->createStub(FeatureCheckerInterface::class);
        $features->method('getEntitlements')->willReturn(['general_ledger' => ['enabled' => true]]);
        $capabilities = new TenantCapabilityResolver($features, $repository);
        /** @var array{source: string, ingresos: array{bruto: float, notasCredito: float, neto: float}, costoVentas: array{monto: float, isEstimate: bool}, utilidadBruta: float, gastosOperativos: array{total: float, porCategoria: list<array{categoria: string, monto: float}>}, utilidadNeta: float, historial: list<array{utilidadNeta: float}>} $result */
        $result = new ProfitAndLossQuery($this->connection, $capabilities)->compute('202606');

        static::assertSame('ledger', $result['source']);
        static::assertSame(1180.0, $result['ingresos']['bruto']);
        static::assertSame(100.0, $result['ingresos']['notasCredito']);
        static::assertSame(1080.0, $result['ingresos']['neto']);
        static::assertSame(400.0, $result['costoVentas']['monto']);
        static::assertFalse($result['costoVentas']['isEstimate']);
        static::assertSame(680.0, $result['utilidadBruta']);
        static::assertSame(300.0, $result['gastosOperativos']['total']);
        static::assertSame(
            [['categoria' => 'Alquileres', 'monto' => 300.0]],
            $result['gastosOperativos']['porCategoria'],
        );
        static::assertSame(380.0, $result['utilidadNeta']);
        static::assertSame(
            -999.0,
            $result['historial'][1]['utilidadNeta'],
            'the previous month is also from the ledger',
        );
    }

    private function createLedger(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE chart_of_accounts (id VARCHAR(36) PRIMARY KEY, code VARCHAR(16), name VARCHAR(255), type VARCHAR(20))',
        );
        $this->connection->executeStatement('CREATE TABLE journal_entry (id VARCHAR(36) PRIMARY KEY, entry_date DATE)');
        $this->connection->executeStatement(
            'CREATE TABLE journal_entry_line (id INTEGER PRIMARY KEY AUTOINCREMENT, journal_entry_id VARCHAR(36), account_id VARCHAR(36), debit DECIMAL(14,2), credit DECIMAL(14,2))',
        );
    }

    private function account(string $id, string $code, string $name, string $type): void
    {
        $this->connection->insert('chart_of_accounts', [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
        ]);
    }

    /** @param list<array{string, int, int}> $lines */
    private function entry(string $id, string $date, array $lines): void
    {
        $this->connection->insert('journal_entry', ['id' => $id, 'entry_date' => $date]);
        foreach ($lines as [$account, $debit, $credit]) {
            $this->connection->insert('journal_entry_line', [
                'journal_entry_id' => $id,
                'account_id' => $account,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }
    }
}
