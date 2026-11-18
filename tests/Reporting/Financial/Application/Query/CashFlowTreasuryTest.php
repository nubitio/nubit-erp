<?php

declare(strict_types=1);

namespace App\Tests\Reporting\Financial\Application\Query;

use App\Reporting\Financial\Application\Query\CashFlowQuery;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Settings\Entity\TenantCapability;
use App\Settings\Repository\TenantCapabilityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/** With Treasury on, the real cash flow comes from the money accounts and the drawers, in one currency. */
#[AllowMockObjectsWithoutExpectations]
final class CashFlowTreasuryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $pdo = $this->connection->getNativeConnection();
        static::assertInstanceOf(\PDO::class, $pdo);
        $pdo->sqliteCreateFunction('DATE_FORMAT', static function (?string $date, string $format): ?string {
            if (null === $date) {
                return null;
            }
            $ts = strtotime($date);

            return false !== $ts ? date(str_replace(['%x', '%v'], ['o', 'W'], $format), $ts) : null;
        });
        foreach ([
            'CREATE TABLE treasury_account (id VARCHAR(36) PRIMARY KEY, kind VARCHAR(20), currency CHAR(3), cash_register_id INT)',
            'CREATE TABLE treasury_movement (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id VARCHAR(36), signed_amount DECIMAL(14,2), currency CHAR(3), occurred_at DATETIME, source_type VARCHAR(32))',
            'CREATE TABLE cash_sessions (id INTEGER PRIMARY KEY, cash_register_id INT)',
            'CREATE TABLE cash_movements (id INTEGER PRIMARY KEY AUTOINCREMENT, cash_session_id INT, movement_type VARCHAR(30), amount DECIMAL(10,2), created_at DATETIME)',
            'CREATE TABLE sales (id VARCHAR(36) PRIMARY KEY, status VARCHAR(30) DEFAULT \'COMPLETED\', due_date DATE, balance_due DECIMAL(12,2), currency CHAR(3))',
            'CREATE TABLE supplier_bill (id VARCHAR(36) PRIMARY KEY, due_date DATE, balance_due DECIMAL(12,2), status VARCHAR(20) DEFAULT \'PENDING\', currency CHAR(3))',
        ] as $sql) {
            $this->connection->executeStatement($sql);
        }
        $this->connection->insert('treasury_account', [
            'id' => 'drawer',
            'kind' => 'cash_register',
            'currency' => 'PEN',
            'cash_register_id' => 1,
        ]);
        $this->connection->insert('treasury_account', [
            'id' => 'bcp',
            'kind' => 'bank',
            'currency' => 'PEN',
            'cash_register_id' => null,
        ]);
        $this->connection->insert('treasury_account', [
            'id' => 'usd',
            'kind' => 'bank',
            'currency' => 'USD',
            'cash_register_id' => null,
        ]);
        $this->connection->insert('cash_sessions', ['id' => 10, 'cash_register_id' => 1]);
    }

    public function testRealFlowCountsBanksWalletsAndDrawersInOneCurrencyAndSkipsWhatIsNotAFlow(): void
    {
        $this->movement('bcp', 1000, 'PEN', 'opening_balance', '2026-06-01 09:00:00'); // not a flow
        $this->movement('bcp', 300, 'PEN', 'sale_payment', '2026-06-03 10:00:00');
        $this->movement('bcp', -150, 'PEN', 'supplier_payment', '2026-06-03 11:00:00');
        $this->movement('bcp', -200, 'PEN', 'transfer', '2026-06-03 12:00:00'); // between own accounts
        $this->movement('usd', 500, 'USD', 'sale_payment', '2026-06-03 10:00:00'); // other currency
        $this->movement('drawer', 999, 'PEN', 'sale_payment', '2026-06-03 10:00:00'); // drawers use their own ledger
        $this->drawer('SALE_IN', 80, '2026-06-04 10:00:00');
        $this->drawer('CASH_OUT', 20, '2026-06-04 11:00:00');
        $this->drawer('REFUND_OUT', 10, '2026-06-30 23:59:59');
        $this->drawer('OPENING', 500, '2026-06-04 07:00:00'); // not a flow

        /** @var array{source: string, currency: string|null, real: list<array{weekKey: string, entradas: float, salidas: float, neto: float}>, proyectado: list<array{cobrosEsperados: float, pagosEsperados: float}>} $result */
        $result = $this->query()->compute('2026-06-01', '2026-06-30');

        static::assertSame('treasury', $result['source']);
        static::assertSame('PEN', $result['currency']);
        $weeks = array_column($result['real'], null, 'weekKey');
        // 2026-06-04 and 2026-06-30 are in weeks 23 and 27: the drawer's sale and cash out, then the refund.
        static::assertSame(380.0, $weeks['2026-23']['entradas'], 'bank sale 300 + drawer sale 80');
        static::assertSame(
            170.0,
            $weeks['2026-23']['salidas'],
            'supplier 150 + drawer cash out 20; the transfer is not a flow',
        );
        static::assertSame(10.0, $weeks['2026-27']['salidas']);
    }

    public function testUsdIsAskedForSeparatelyAndNeverMixedWithSoles(): void
    {
        $this->movement('usd', 500, 'USD', 'sale_payment', '2026-06-03 10:00:00');
        $this->movement('bcp', 300, 'PEN', 'sale_payment', '2026-06-03 10:00:00');

        /** @var array{source: string, currency: string|null, real: list<array{weekKey: string, entradas: float, salidas: float, neto: float}>, proyectado: list<array{cobrosEsperados: float, pagosEsperados: float}>} $result */
        $result = $this->query()->compute('2026-06-01', '2026-06-30', 'usd');

        static::assertSame('USD', $result['currency']);
        static::assertSame(500.0, $result['real'][0]['entradas']);
    }

    public function testTheProjectionIsAlsoOfTheChosenCurrency(): void
    {
        $due = new \DateTimeImmutable('+10 days')->format('Y-m-d');
        $this->connection->insert('sales', [
            'id' => 's1',
            'due_date' => $due,
            'balance_due' => 100,
            'currency' => 'PEN',
        ]);
        $this->connection->insert('sales', [
            'id' => 's2',
            'due_date' => $due,
            'balance_due' => 900,
            'currency' => 'USD',
        ]);
        $this->connection->insert('supplier_bill', [
            'id' => 'b1',
            'due_date' => $due,
            'balance_due' => 40,
            'currency' => 'PEN',
        ]);

        /** @var array{source: string, currency: string|null, real: list<array{weekKey: string, entradas: float, salidas: float, neto: float}>, proyectado: list<array{cobrosEsperados: float, pagosEsperados: float}>} $result */
        $result = $this->query()->compute();

        static::assertSame(100.0, $result['proyectado'][0]['cobrosEsperados']);
        static::assertSame(40.0, $result['proyectado'][0]['pagosEsperados']);
    }

    private function query(): CashFlowQuery
    {
        $repository = $this->createMock(TenantCapabilityRepository::class);
        $repository
            ->method('allByKey')
            ->willReturn(['treasury.accounts' => new TenantCapability('treasury.accounts')->setEnabled(true)]);

        return new CashFlowQuery(
            $this->connection,
            new TenantCapabilityResolver($this->createStub(FeatureCheckerInterface::class), $repository),
        );
    }

    private function movement(string $account, int $amount, string $currency, string $source, string $at): void
    {
        $this->connection->insert('treasury_movement', [
            'account_id' => $account,
            'signed_amount' => $amount,
            'currency' => $currency,
            'source_type' => $source,
            'occurred_at' => $at,
        ]);
    }

    private function drawer(string $type, int $amount, string $at): void
    {
        $this->connection->insert('cash_movements', [
            'cash_session_id' => 10,
            'movement_type' => $type,
            'amount' => $amount,
            'created_at' => $at,
        ]);
    }
}
