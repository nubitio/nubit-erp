<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Inventory\Core\Service\ReconciliationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ReconciliationService.
 *
 * All dependencies are mocked — no real DB involved.
 *
 * Coverage targets:
 *   - run()         : no-pairs (empty), ok pairs, drift pairs, error path
 *   - reconcileOne(): OK (within tolerance), DRIFT (positive + negative), zero-delta
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ReconciliationServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // reconcileOne — OK (no drift: ledger == stock_items)
    // -------------------------------------------------------------------------

    public function testReconcileOneReturnsOkWhenLedgerMatchesStockItems(): void
    {
        $connection = $this->connectionWithLedgerSum('10.000000');

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));

        $result = $service->reconcileOne('wh-1', 'prod-1', '10.000000');

        self::assertSame('ok', $result);
    }

    // -------------------------------------------------------------------------
    // reconcileOne — OK with zero delta (exact zero diff)
    // -------------------------------------------------------------------------

    public function testReconcileOneReturnsOkWhenDeltaIsExactlyZero(): void
    {
        $connection = $this->connectionWithLedgerSum('0');

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));

        $result = $service->reconcileOne('wh-1', 'prod-zero', '0.000000');

        self::assertSame('ok', $result);
    }

    // -------------------------------------------------------------------------
    // reconcileOne — OK with diff within tolerance (< 0.000001)
    // -------------------------------------------------------------------------

    public function testReconcileOneReturnsOkWhenDiffIsWithinTolerance(): void
    {
        // Diff = 10.0000004 - 10.000000 = 0.0000004 < tolerance 0.000001
        $connection = $this->connectionWithLedgerSum('10.0000004');

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));

        $result = $service->reconcileOne('wh-1', 'prod-tol', '10.000000');

        self::assertSame('ok', $result);
    }

    // -------------------------------------------------------------------------
    // reconcileOne — DRIFT (positive): ledger > stock_items
    // -------------------------------------------------------------------------

    public function testReconcileOneReturnsDriftWhenLedgerExceedsStockItems(): void
    {
        // Diff = 15 - 10 = 5 → well above tolerance
        $connection = $this->connectionWithLedgerSum('15.000000');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Inventory drift detected',
                self::callback(
                    static fn(array $ctx): bool => (
                        $ctx['warehouse_id'] === 'wh-2'
                        && $ctx['product_id'] === 'prod-2'
                        && $ctx['ledger_sum'] === '15.000000'
                        && $ctx['stock_items_qty'] === '10.000000'
                    ),
                ),
            );

        $service = new ReconciliationService($connection, $logger);

        $result = $service->reconcileOne('wh-2', 'prod-2', '10.000000');

        self::assertSame('drift', $result);
    }

    // -------------------------------------------------------------------------
    // reconcileOne — DRIFT (negative): ledger < stock_items
    // -------------------------------------------------------------------------

    public function testReconcileOneReturnsDriftWhenLedgerIsBelowStockItems(): void
    {
        // Diff = 3 - 10 = -7 → abs = 7 > tolerance
        $connection = $this->connectionWithLedgerSum('3.000000');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $service = new ReconciliationService($connection, $logger);

        $result = $service->reconcileOne('wh-3', 'prod-3', '10.000000');

        self::assertSame('drift', $result);
    }

    // -------------------------------------------------------------------------
    // reconcileOne — audit INSERT is always called
    // -------------------------------------------------------------------------

    public function testReconcileOneAlwaysInsertsAuditRow(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('5.000000');
        $connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('INSERT INTO inventory_reconciliation_runs'),
                self::callback(
                    static fn(array $params): bool => (
                        $params['w'] === 'wh-audit'
                        && $params['p'] === 'prod-audit'
                        && $params['ledger_sum'] === '5.000000'
                        && $params['stock_items_qty'] === '5.000000'
                        && isset($params['id'])
                        && isset($params['run_at'])
                    ),
                ),
            );

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));
        $service->reconcileOne('wh-audit', 'prod-audit', '5.000000');
    }

    // -------------------------------------------------------------------------
    // reconcileOne — ledger sum is null / falsy (COALESCE fallback)
    // -------------------------------------------------------------------------

    public function testReconcileOneHandlesFalsyLedgerSumAsZero(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createStub(Connection::class);
        // fetchOne returns false (no rows at all — should be treated as 0)
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('executeStatement')->willReturn(0);

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));

        // stock_items says 0 → ledger sum treated as 0 → no drift
        $result = $service->reconcileOne('wh-null', 'prod-null', '0.000000');

        self::assertSame('ok', $result);
    }

    // -------------------------------------------------------------------------
    // run() — empty stock_items → returns zeroed counters, no reconcileOne calls
    // -------------------------------------------------------------------------

    public function testRunWithNoStockItemPairsReturnsAllZeroCounts(): void
    {
        $connection = $this->connectionForRun([]);

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));

        $result = $service->run();

        self::assertSame(['ok' => 0, 'drift' => 0, 'error' => 0], $result);
    }

    // -------------------------------------------------------------------------
    // run() — all pairs OK (no drift)
    // -------------------------------------------------------------------------

    public function testRunWithAllOkPairsReturnsCorrectCounts(): void
    {
        $pairs = [
            ['warehouse_id' => 'wh-a', 'product_id' => 'prod-a', 'qty_on_hand' => '10.000000'],
            ['warehouse_id' => 'wh-a', 'product_id' => 'prod-b', 'qty_on_hand' => '20.000000'],
        ];

        // Both ledger sums match stock_items exactly → no drift
        $connection = $this->connectionForRun($pairs, ['10.000000', '20.000000']);

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));

        $result = $service->run();

        self::assertSame(2, $result['ok']);
        self::assertSame(0, $result['drift']);
        self::assertSame(0, $result['error']);
    }

    // -------------------------------------------------------------------------
    // run() — one pair has positive drift
    // -------------------------------------------------------------------------

    public function testRunWithPositiveDriftPairReturnsCorrectCounts(): void
    {
        $pairs = [
            ['warehouse_id' => 'wh-d', 'product_id' => 'prod-d', 'qty_on_hand' => '5.000000'],
        ];

        // Ledger says 99 → stock_items says 5 → big drift
        $connection = $this->connectionForRun($pairs, ['99.000000']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $service = new ReconciliationService($connection, $logger);

        $result = $service->run();

        self::assertSame(0, $result['ok']);
        self::assertSame(1, $result['drift']);
        self::assertSame(0, $result['error']);
    }

    // -------------------------------------------------------------------------
    // run() — one pair has negative drift
    // -------------------------------------------------------------------------

    public function testRunWithNegativeDriftPairReturnsCorrectCounts(): void
    {
        $pairs = [
            ['warehouse_id' => 'wh-e', 'product_id' => 'prod-e', 'qty_on_hand' => '50.000000'],
        ];

        // Ledger sum is 1 but stock_items says 50 → negative drift
        $connection = $this->connectionForRun($pairs, ['1.000000']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $service = new ReconciliationService($connection, $logger);

        $result = $service->run();

        self::assertSame(0, $result['ok']);
        self::assertSame(1, $result['drift']);
        self::assertSame(0, $result['error']);
    }

    // -------------------------------------------------------------------------
    // run() — exception in reconcileOne increments error counter
    // -------------------------------------------------------------------------

    public function testRunIncrementsErrorCountOnException(): void
    {
        $pairs = [
            ['warehouse_id' => 'wh-err', 'product_id' => 'prod-err', 'qty_on_hand' => '10.000000'],
        ];

        /** @var Connection&MockObject $connection */
        $connection = $this->createStub(Connection::class);

        // fetchAllAssociative powers the QBuilder result
        $resultMock = $this->createStub(Result::class);
        $resultMock->method('fetchAllAssociative')->willReturn($pairs);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('executeQuery')->willReturn($resultMock);

        $connection->method('createQueryBuilder')->willReturn($qb);

        // fetchOne (ledger sum) throws a DB exception
        $connection->method('fetchOne')->willThrowException(new \RuntimeException('DB connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Reconciliation error',
                self::callback(
                    static fn(array $ctx): bool => $ctx['warehouse_id'] === 'wh-err'
                    && $ctx['product_id'] === 'prod-err'
                    && str_contains($ctx['error'], 'DB connection lost'),
                ),
            );

        $service = new ReconciliationService($connection, $logger);

        $result = $service->run();

        self::assertSame(0, $result['ok']);
        self::assertSame(0, $result['drift']);
        self::assertSame(1, $result['error']);
    }

    // -------------------------------------------------------------------------
    // run() — warehouseId filter is forwarded to query builder
    // -------------------------------------------------------------------------

    public function testRunWithWarehouseFilterPassesParameterToQuery(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createStub(Connection::class);

        $resultMock = $this->createStub(Result::class);
        $resultMock->method('fetchAllAssociative')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->expects(self::once())->method('andWhere')->with('warehouse_id = :w')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->with('w', 'wh-filter')->willReturnSelf();
        $qb->method('executeQuery')->willReturn($resultMock);

        $connection->method('createQueryBuilder')->willReturn($qb);

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));
        $service->run('wh-filter');
    }

    // -------------------------------------------------------------------------
    // run() — productId filter is forwarded to query builder
    // -------------------------------------------------------------------------

    public function testRunWithProductFilterPassesParameterToQuery(): void
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createStub(Connection::class);

        $resultMock = $this->createStub(Result::class);
        $resultMock->method('fetchAllAssociative')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->expects(self::once())->method('andWhere')->with('product_id = :p')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->with('p', 'prod-filter')->willReturnSelf();
        $qb->method('executeQuery')->willReturn($resultMock);

        $connection->method('createQueryBuilder')->willReturn($qb);

        $service = new ReconciliationService($connection, $this->createStub(LoggerInterface::class));
        $service->run(null, 'prod-filter');
    }

    // -------------------------------------------------------------------------
    // run() — mixed result: ok + drift + error in a single run
    // -------------------------------------------------------------------------

    public function testRunReturnsMixedCountsForMixedPairs(): void
    {
        $pairs = [
            ['warehouse_id' => 'wh-m', 'product_id' => 'ok-prod', 'qty_on_hand' => '10.000000'],
            ['warehouse_id' => 'wh-m', 'product_id' => 'drift-prod', 'qty_on_hand' => '10.000000'],
        ];

        // Ledger sums: ok-prod matches (10), drift-prod diverges (999)
        $connection = $this->connectionForRun($pairs, ['10.000000', '999.000000']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning'); // one drift warning

        $service = new ReconciliationService($connection, $logger);

        $result = $service->run();

        self::assertSame(1, $result['ok']);
        self::assertSame(1, $result['drift']);
        self::assertSame(0, $result['error']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a Connection mock that returns the given ledger sum for fetchOne
     * and accepts any executeStatement call.
     *
     * @return Connection&MockObject
     */
    private function connectionWithLedgerSum(string $sum): Connection
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($sum);
        $connection->method('executeStatement')->willReturn(0);

        return $connection;
    }

    /**
     * Build a Connection mock for run() tests.
     *
     * @param list<array{warehouse_id: string, product_id: string, qty_on_hand: string}> $pairs
     * @param list<string> $ledgerSums Consecutive return values for fetchOne (one per pair)
     *
     * @return Connection&MockObject
     */
    private function connectionForRun(array $pairs, array $ledgerSums = []): Connection
    {
        /** @var Connection&MockObject $connection */
        $connection = $this->createMock(Connection::class);

        $resultMock = $this->createStub(Result::class);
        $resultMock->method('fetchAllAssociative')->willReturn($pairs);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('executeQuery')->willReturn($resultMock);

        $connection->method('createQueryBuilder')->willReturn($qb);

        if ($ledgerSums !== []) {
            $connection->method('fetchOne')->willReturnOnConsecutiveCalls(...$ledgerSums);
        }

        $connection->method('executeStatement')->willReturn(0);

        return $connection;
    }
}
