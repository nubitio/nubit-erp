<?php

declare(strict_types=1);

namespace App\Tests\Sales\Repository;

use App\Sales\Repository\DashboardRepository;
use App\Settings\Service\SunatStateService;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Cache\CacheManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for DashboardRepository.
 *
 * Design notes:
 *  - DashboardRepository is `readonly` (not final) → mockable.
 *  - Connection, SettingsService, CacheManager are all concrete classes used
 *    via PHPUnit's createMock(). Connection is readonly but not final.
 *  - CacheManager is readonly but not final → mockable.
 *  - SettingsService is readonly but not final → mockable.
 *  - All DB queries inside buildOverview() / buildLiteOverview() go through
 *    safeQuery() which catches Throwable — so throwing on specific calls is
 *    the key technique for the "partial" / failedSegments path.
 */
#[AllowMockObjectsWithoutExpectations]
final class DashboardRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private SunatStateService&MockObject $sunatState;
    private CacheManager&MockObject $cache;
    private DashboardRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->sunatState = $this->createMock(SunatStateService::class);
        $this->cache = $this->createMock(CacheManager::class);

        // By default: no receipt filter requirement
        $this->sunatState->method('isReceiptRequired')->willReturn(false);

        $this->repository = new DashboardRepository($this->connection, $this->sunatState, $this->cache);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Configure the connection to return sensible zero data for all queries.
     * fetchAssociative → [], fetchAllAssociative → [].
     */
    private function stubConnectionAllZero(): void
    {
        $this->connection->method('fetchAssociative')->willReturn([]);
        $this->connection->method('fetchAllAssociative')->willReturn([]);
    }

    /**
     * Configure cache to always miss (returns null) and accept any set().
     */
    private function stubCacheMiss(): void
    {
        $this->cache->method('get')->willReturn(null);
    }

    /**
     * Configure cache to always hit with $value.
     *
     * @param array<string, mixed> $value
     */
    private function stubCacheHit(array $value): void
    {
        $this->cache->method('get')->willReturn($value);
    }

    // =========================================================================
    // Cache layer — getOverview()
    // =========================================================================

    /** @test (1) getOverview() — cache hit → returns cached result, NO DB calls */
    public function testGetOverviewCacheHitReturnsWithoutDbCall(): void
    {
        $cached = ['summary' => ['salesToday' => 999.0], 'meta' => ['mode' => 'full']];
        $this->stubCacheHit($cached);

        $this->connection->expects(self::never())->method('fetchAssociative');
        $this->connection->expects(self::never())->method('fetchAllAssociative');

        $result = $this->repository->getOverview('branch-1');

        self::assertSame($cached, $result);
    }

    /** @test (2) getOverview() — cache miss → calls buildOverview(), stores result */
    public function testGetOverviewCacheMissCallsBuildAndStoresResult(): void
    {
        $this->stubCacheMiss();
        $this->stubConnectionAllZero();

        $this->cache->expects(self::once())->method('set')->with('dashboard_overview_branch-1', self::isArray(), 120);

        $result = $this->repository->getOverview('branch-1');

        self::assertArrayHasKey('summary', $result);
        self::assertArrayHasKey('meta', $result);
    }

    // =========================================================================
    // Cache layer — getLiteOverview()
    // =========================================================================

    /** @test (3) getLiteOverview() — cache hit → returns cached result */
    public function testGetLiteOverviewCacheHitReturnsWithoutDbCall(): void
    {
        $cached = ['summary' => ['salesToday' => 42.0], 'meta' => ['mode' => 'lite']];
        $this->stubCacheHit($cached);

        $this->connection->expects(self::never())->method('fetchAssociative');

        $result = $this->repository->getLiteOverview('branch-2');
        self::assertSame($cached, $result);
    }

    /** @test (4) getLiteOverview() — cache miss → calls buildLiteOverview(), stores result */
    public function testGetLiteOverviewCacheMissCallsBuildAndStoresResult(): void
    {
        $this->stubCacheMiss();
        $this->stubConnectionAllZero();

        $this->cache->expects(self::once())->method('set')->with('dashboard_lite_branch-2', self::isArray(), 60);

        $result = $this->repository->getLiteOverview('branch-2');

        self::assertArrayHasKey('summary', $result);
        self::assertArrayHasKey('meta', $result);
    }

    // =========================================================================
    // invalidate()
    // =========================================================================

    /** @test (5) invalidate() — calls cache->forget() for both keys */
    public function testInvalidateCallsForgetForBothKeys(): void
    {
        $this->cache
            ->expects(self::exactly(2))
            ->method('forget')
            ->willReturnCallback(function (string $key): void {
                static $calls = [];
                $calls[] = $key;
            });

        $this->repository->invalidate('branch-3');
    }

    // =========================================================================
    // buildOverview() — result structure
    // =========================================================================

    /** @test (6) getOverview() — DB calls succeed → result has expected top-level keys */
    public function testGetOverviewDbSuccessContainsExpectedKeys(): void
    {
        $this->stubCacheMiss();
        $this->stubConnectionAllZero();

        $result = $this->repository->getOverview('branch-1');

        foreach ([
            'summary',
            'salesByDay',
            'salesByMonth',
            'paymentMethods',
            'paymentMethodTrend',
            'topProducts',
            'purchasesSummary',
            'purchasesByDay',
            'topPurchasedProducts',
            'topCustomers',
            'voucherTypes',
            'lowStockProducts',
            'meta',
        ] as $key) {
            self::assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    /** @test (7) getOverview() — when a DB segment throws → meta.partial=true + failedSegments */
    public function testGetOverviewDbFailureMarksPartialAndAddsFailedSegment(): void
    {
        $this->stubCacheMiss();
        $callCount = 0;

        // Let the first fetchAssociative call throw to simulate 'summary' failure
        $this->connection
            ->method('fetchAssociative')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new RuntimeException('DB error on summary');
                }
                return [];
            });
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->repository->getOverview('branch-1');

        self::assertTrue($result['meta']['partial']);
        self::assertNotEmpty($result['meta']['failedSegments']);
    }

    // =========================================================================
    // safeQuery() — partial results
    // =========================================================================

    /** @test (8) When a DB segment throws, it is added to failedSegments */
    public function testSafeQueryAddsSegmentToFailedSegmentsOnException(): void
    {
        $this->stubCacheMiss();

        // Make all fetchAllAssociative calls throw
        $this->connection->method('fetchAllAssociative')->willThrowException(new RuntimeException('segment failure'));
        $this->connection->method('fetchAssociative')->willReturn([]);

        $result = $this->repository->getOverview('branch-1');

        self::assertTrue($result['meta']['partial']);
        // Several segments use fetchAllAssociative — all should be in failedSegments
        self::assertContains('salesByDay', $result['meta']['failedSegments']);
    }

    // =========================================================================
    // receiptFilter() — via getLiteOverview()
    // =========================================================================

    /** @test (9) getLiteOverview() with RECEIPT_REQUIRED truthy → SQL includes EXISTS subquery */
    public function testGetLiteOverviewWithReceiptRequiredIncludesExistsFilter(): void
    {
        $this->stubCacheMiss();

        // Override sunat state mock for this test
        $sunatState = $this->createMock(SunatStateService::class);
        $sunatState->method('isReceiptRequired')->willReturn(true);

        $capturedSqls = [];
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql) use (&$capturedSqls): array {
                $capturedSqls[] = $sql;
                return [];
            });
        $this->connection->method('fetchAssociative')->willReturn([]);

        $repo = new DashboardRepository($this->connection, $sunatState, $this->cache);
        $repo->getLiteOverview('branch-1');

        // At least one SQL should contain the EXISTS subquery
        $found = array_filter($capturedSqls, static fn(string $sql): bool => str_contains($sql, 'EXISTS'));
        self::assertNotEmpty($found, 'Expected EXISTS subquery in at least one SQL query');
    }

    /** @test (10) getLiteOverview() with RECEIPT_REQUIRED falsy → SQL does NOT include EXISTS */
    public function testGetLiteOverviewWithoutReceiptRequiredExcludesExistsFilter(): void
    {
        $this->stubCacheMiss();

        $capturedSqls = [];
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql) use (&$capturedSqls): array {
                $capturedSqls[] = $sql;
                return [];
            });
        $this->connection->method('fetchAssociative')->willReturn([]);

        $this->repository->getLiteOverview('branch-1');

        $found = array_filter($capturedSqls, static fn(string $sql): bool => str_contains($sql, 'EXISTS'));
        self::assertEmpty($found, 'Expected no EXISTS subquery when RECEIPT_REQUIRED is falsy');
    }

    // =========================================================================
    // buildLiteOverview() — summary calculations
    // =========================================================================

    /** @test (11) getLiteOverview() DB success → summary has expected keys with correct values */
    public function testGetLiteOverviewDbSuccessContainsExpectedSummaryKeys(): void
    {
        $this->stubCacheMiss();

        // Return realistic data for summaryLite
        $this->connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            // summaryLite call
            [
                'sales_today' => '150.00',
                'sales_yesterday' => '100.00',
                'sales_week' => '700.00',
                'invoices_today' => '5',
            ],
            // refundsLite call
            ['refunds_today' => '10.00'],
        );
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->repository->getLiteOverview('branch-1');

        self::assertArrayHasKey('summary', $result);
        $summary = $result['summary'];
        self::assertSame(150.0, $summary['salesToday']);
        self::assertSame(100.0, $summary['salesYesterday']);
        self::assertSame(700.0, $summary['salesWeek']);
        self::assertSame(5, $summary['invoicesToday']);
        self::assertSame(30.0, $summary['avgTicketToday']); // 150/5
        self::assertSame(10.0, $summary['refundsToday']);
        self::assertSame(140.0, $summary['netSalesToday']); // 150 - 10
        self::assertEqualsWithDelta(50.0, (float) $summary['dailyGrowth'], 0.001); // (150-100)/100*100
    }

    /** @test (12) avgTicketToday = 0 when invoicesToday = 0 */
    public function testGetLiteOverviewAvgTicketTodayIsZeroWhenNoInvoices(): void
    {
        $this->stubCacheMiss();

        $this->connection->method('fetchAssociative')->willReturnOnConsecutiveCalls([
            'sales_today' => '200.00',
            'sales_yesterday' => '100.00',
            'sales_week' => '600.00',
            'invoices_today' => '0',
        ], ['refunds_today' => '0.00']);
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->repository->getLiteOverview('branch-1');
        $summary = $result['summary'];

        self::assertSame(0.0, $summary['avgTicketToday']);
    }

    /** @test (13) dailyGrowth = null when salesYesterday = 0 */
    public function testGetLiteOverviewDailyGrowthIsNullWhenYesterdayZero(): void
    {
        $this->stubCacheMiss();

        $this->connection->method('fetchAssociative')->willReturnOnConsecutiveCalls([
            'sales_today' => '100.00',
            'sales_yesterday' => '0.00',
            'sales_week' => '500.00',
            'invoices_today' => '3',
        ], ['refunds_today' => '0.00']);
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->repository->getLiteOverview('branch-1');
        $summary = $result['summary'];

        self::assertNull($summary['dailyGrowth']);
    }

    // =========================================================================
    // buildMeta() — via both methods
    // =========================================================================

    /** @test (14) meta.mode = 'full' for getOverview() */
    public function testGetOverviewMetaModeIsFull(): void
    {
        $this->stubCacheMiss();
        $this->stubConnectionAllZero();

        $result = $this->repository->getOverview('branch-1');
        self::assertSame('full', $result['meta']['mode']);
    }

    /** @test (15) meta.mode = 'lite' for getLiteOverview() */
    public function testGetLiteOverviewMetaModeIsLite(): void
    {
        $this->stubCacheMiss();
        $this->stubConnectionAllZero();

        $result = $this->repository->getLiteOverview('branch-1');
        self::assertSame('lite', $result['meta']['mode']);
    }

    /** @test (16) meta.partial = false when no failures */
    public function testGetOverviewMetaPartialIsFalseWhenNoFailures(): void
    {
        $this->stubCacheMiss();
        $this->stubConnectionAllZero();

        $result = $this->repository->getOverview('branch-1');
        self::assertFalse($result['meta']['partial']);
        self::assertEmpty($result['meta']['failedSegments']);
    }

    // =========================================================================
    // buildOverview() — avgTicket and growth calculations
    // =========================================================================

    /** @test avgTicket is computed from month totals */
    public function testGetOverviewAvgTicketCalculatedFromMonthTotals(): void
    {
        $this->stubCacheMiss();

        // summaryLite returns with invoicesThisMonth=10, salesThisMonth=500
        $this->connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            // summary query
            [
                'sales_today' => '50.00',
                'sales_yesterday' => '40.00',
                'sales_week' => '300.00',
                'sales_month' => '500.00',
                'sales_previous_month' => '400.00',
                'invoices_today' => '2',
                'invoices_month' => '10',
            ],
            // customers query
            ['customers_month' => '5', 'customers_week' => '3'],
            // purchaseSummary
            [
                'purchases_today' => '0',
                'purchases_week' => '0',
                'purchases_month' => '0',
                'purchases_previous_month' => '0',
                'receipts_today' => '0',
                'receipts_month' => '0',
            ],
            // refunds
            ['refunds_today' => '0', 'refunds_month' => '0'],
            ['total' => '120.00', 'count' => '2'],
            ['total' => '80.00', 'count' => '1'],
            ['count' => '3'],
        );
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->repository->getOverview('branch-1');
        $summary = $result['summary'];

        self::assertSame(50.0, $summary['avgTicket']); // 500/10
        self::assertSame(120.0, $summary['receivablesTotal']);
        self::assertSame(80.0, $summary['payablesTotal']);
        self::assertSame(3, $summary['pendingSunatCount']);
        self::assertEqualsWithDelta(25.0, (float) $summary['monthGrowth'], 0.001); // (500-400)/400*100
    }

    /** @test monthGrowth is null when salesLastMonth = 0 */
    public function testGetOverviewMonthGrowthIsNullWhenLastMonthZero(): void
    {
        $this->stubCacheMiss();

        $this->connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            [
                'sales_today' => '50.00',
                'sales_yesterday' => '40.00',
                'sales_week' => '300.00',
                'sales_month' => '500.00',
                'sales_previous_month' => '0.00', // zero
                'invoices_today' => '0',
                'invoices_month' => '0',
            ],
            ['customers_month' => '0', 'customers_week' => '0'],
            [
                'purchases_today' => '0',
                'purchases_week' => '0',
                'purchases_month' => '0',
                'purchases_previous_month' => '0',
                'receipts_today' => '0',
                'receipts_month' => '0',
            ],
            ['refunds_today' => '0', 'refunds_month' => '0'],
            [],
            [],
            [],
        );
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->repository->getOverview('branch-1');
        self::assertNull($result['summary']['monthGrowth']);
    }

    /** @test getLiteOverview() meta.partial true when a lite segment fails */
    public function testGetLiteOverviewMetaPartialTrueWhenSegmentFails(): void
    {
        $this->stubCacheMiss();

        $this->connection
            ->method('fetchAllAssociative')
            ->willThrowException(new RuntimeException('lite query failure'));
        $this->connection->method('fetchAssociative')->willReturn([]);

        $result = $this->repository->getLiteOverview('branch-1');

        self::assertTrue($result['meta']['partial']);
        self::assertNotEmpty($result['meta']['failedSegments']);
    }
}
