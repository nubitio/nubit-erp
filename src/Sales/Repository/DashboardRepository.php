<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use Nubit\Platform\Cache\CacheManager;
use App\Settings\Service\SunatStateService;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

readonly class DashboardRepository
{
    /** Full overview cached for 2 minutes — short enough to feel live. */
    private const int CACHE_TTL_FULL = 120;

    /** Lite overview (mobile) cached for 60 seconds. */
    private const int CACHE_TTL_LITE = 60;

    public function __construct(
        private Connection $connection,
        private SunatStateService $sunatState,
        private CacheManager $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(string $branchId): array
    {
        $cacheKey = $this->overviewKey($branchId);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->buildOverview($branchId);
        $this->cache->set($cacheKey, $result, self::CACHE_TTL_FULL);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLiteOverview(string $branchId): array
    {
        $cacheKey = $this->liteKey($branchId);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->buildLiteOverview($branchId);
        $this->cache->set($cacheKey, $result, self::CACHE_TTL_LITE);

        return $result;
    }

    /**
     * Invalidate the dashboard cache for a branch (call after a sale is posted).
     */
    public function invalidate(string $branchId): void
    {
        $this->cache->forget($this->overviewKey($branchId));
        $this->cache->forget($this->liteKey($branchId));
    }

    /**
     * Returns the tenant-agnostic cache key for the full overview.
     * CacheManager.scopedKey() will prepend t.{tenantName}. at runtime.
     * Format: dashboard_overview_{branchId}
     */
    private function overviewKey(string $branchId): string
    {
        return "dashboard_overview_{$branchId}";
    }

    /**
     * Returns the tenant-agnostic cache key for the lite overview.
     * CacheManager.scopedKey() will prepend t.{tenantName}. at runtime.
     * Format: dashboard_lite_{branchId}
     */
    private function liteKey(string $branchId): string
    {
        return "dashboard_lite_{$branchId}";
    }

    // -------------------------------------------------------------------------
    // Internal builders (no caching — called by the public getters above)
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function buildOverview(string $branchId): array
    {
        $ctx = $this->buildQueryContext($branchId);
        $params = $ctx['params'];
        $receiptFilter = $ctx['receiptFilterSales'];
        $failedSegments = [];

        $summary = $this->safeQuery('summary', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN bds.net_sales ELSE 0 END) AS sales_today,
                SUM(CASE WHEN td.date = :yesterday THEN bds.net_sales ELSE 0 END) AS sales_yesterday,
                SUM(CASE WHEN td.date >= :last7days THEN bds.net_sales ELSE 0 END) AS sales_week,
                SUM(
                    CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN bds.net_sales ELSE 0 END
                ) AS sales_month,
                SUM(
                    CASE WHEN td.year = :previousYear AND td.month = :previousMonth THEN bds.net_sales ELSE 0 END
                ) AS sales_previous_month,
                SUM(CASE WHEN td.date = :today THEN bds.sales_count ELSE 0 END) AS invoices_today,
                SUM(
                    CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN bds.sales_count ELSE 0 END
                ) AS invoices_month
            FROM branch_daily_summary bds
            INNER JOIN time_dimension td ON td.date = bds.summary_date
            WHERE bds.branch_id = :branchId
        ", $params) ?: [], [], $failedSegments);

        // Replaced non-sargable CONVERT_TZ JOIN on time_dimension with a direct
        // UTC range filter on issued_at — lets the idx_sales_branch_issued index
        // cover the full WHERE clause without a function call on every row.
        $customers = $this->safeQuery('customers', fn () => $this->connection->fetchAssociative("
            SELECT
                COUNT(
                    DISTINCT CASE
                        WHEN s.issued_at >= :monthStartUtc AND s.issued_at < :monthEndUtc
                        THEN s.customer_id
                    END
                ) AS customers_month,
                COUNT(
                    DISTINCT CASE
                        WHEN s.issued_at >= :weekStartUtc
                        THEN s.customer_id
                    END
                ) AS customers_week
            FROM sales s
            WHERE s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
              AND s.branch_id = :branchId
              AND s.issued_at >= :monthStartUtc
        ", $params) ?: [], [], $failedSegments);

        $salesByDay = $this->safeQuery('salesByDay', fn () => $this->connection->fetchAllAssociative("
            SELECT
                td.day AS day,
                td.day_name AS day_name,
                COALESCE(SUM(s.net_sales), 0) AS total
            FROM time_dimension td
            LEFT JOIN branch_daily_summary s
                ON td.date = s.summary_date
                AND s.branch_id = :branchId
            WHERE td.year = :currentYear
              AND td.month = :currentMonth
            GROUP BY td.id, td.day
            ORDER BY td.day
        ", $params), [], $failedSegments);

        $salesByMonth = $this->safeQuery('salesByMonth', fn () => $this->connection->fetchAllAssociative("
            SELECT
                CONCAT(LEFT(td.month_name, 3), ' ', td.year) AS label,
                td.year,
                td.month,
                COALESCE(SUM(s.net_sales), 0) AS total
            FROM time_dimension td
            LEFT JOIN branch_daily_summary s
                ON td.date = s.summary_date
                AND s.branch_id = :branchId
            WHERE td.date >= :monthsBackDate
              AND td.date <= :today
            GROUP BY td.year, td.month
            ORDER BY td.year, td.month
        ", $params), [], $failedSegments);

        // Reads live sale_payments — payment_method_daily_summary has no populator yet.
        $paymentMethodShare = $this->safeQuery('paymentMethods', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(pm.name, 'Sin método') AS method,
                COALESCE(SUM(sp.amount), 0) AS total
            FROM sale_payments sp
                LEFT JOIN payment_method pm ON sp.payment_method_id = pm.id
                INNER JOIN sales s ON sp.sale_id = s.id
            WHERE s.branch_id = :branchId
              AND s.issued_at >= :last30daysUtc
              AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
            GROUP BY pm.id, pm.name
            ORDER BY total DESC
        ", $params), [], $failedSegments);

        // Reads live sale_payments grouped by day — payment_method_daily_summary has no populator yet.
        $paymentMethodTrendRaw = $this->safeQuery(
            'paymentMethodTrend',
            fn () => $this->connection->fetchAllAssociative("
                SELECT
                    DATE(CONVERT_TZ(s.issued_at, '+00:00', '-05:00')) AS date_value,
                    DATE_FORMAT(DATE(CONVERT_TZ(s.issued_at, '+00:00', '-05:00')), '%d %b') AS label,
                    COALESCE(pm.name, 'Sin método') AS method,
                    COALESCE(SUM(sp.amount), 0) AS total
                FROM sale_payments sp
                    LEFT JOIN payment_method pm ON sp.payment_method_id = pm.id
                    INNER JOIN sales s ON sp.sale_id = s.id
                WHERE s.branch_id = :branchId
                  AND s.issued_at >= :last30daysUtc
                  AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
                GROUP BY date_value, pm.id, pm.name
                ORDER BY date_value
            ", $params),
            [],
            $failedSegments
        );

        // Reads live sale_items — product_daily_summary has no populator yet.
        $topProducts = $this->safeQuery('topProducts', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(p.descripcion, si.name_snapshot, 'Sin producto') AS product,
                SUM(si.qty) AS quantity,
                SUM(si.net_amount) AS total
            FROM sale_items si
                LEFT JOIN producto p ON si.product_id = p.producto_id
                INNER JOIN sales s ON si.sale_id = s.id
            WHERE s.branch_id = :branchId
              AND s.issued_at >= :last30daysUtc
              AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
            GROUP BY si.product_id, p.descripcion, si.name_snapshot
            ORDER BY total DESC
            LIMIT 5
        ", $params), [], $failedSegments);

        $purchaseSummary = $this->safeQuery('purchaseSummary', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN pds.grand_total ELSE 0 END) AS purchases_today,
                SUM(CASE WHEN td.date >= :last7days THEN pds.grand_total ELSE 0 END) AS purchases_week,
                SUM(
                    CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN pds.grand_total ELSE 0 END
                ) AS purchases_month,
                SUM(
                    CASE WHEN td.year = :previousYear AND td.month = :previousMonth THEN pds.grand_total ELSE 0 END
                ) AS purchases_previous_month,
                SUM(CASE WHEN td.date = :today THEN pds.receipts_count ELSE 0 END) AS receipts_today,
                SUM(
                    CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN pds.receipts_count ELSE 0 END
                ) AS receipts_month
            FROM purchases_daily_summary pds
            INNER JOIN time_dimension td ON td.date = pds.summary_date
            WHERE pds.branch_id = :branchId
        ", $params) ?: [], [], $failedSegments);

        $purchasesByDay = $this->safeQuery('purchasesByDay', fn () => $this->connection->fetchAllAssociative("
            SELECT
                td.day AS day,
                td.day_name AS day_name,
                COALESCE(SUM(pds.grand_total), 0) AS total
            FROM time_dimension td
            LEFT JOIN purchases_daily_summary pds
                ON td.date = pds.summary_date
                AND pds.branch_id = :branchId
            WHERE td.year = :currentYear
              AND td.month = :currentMonth
            GROUP BY td.id, td.day
            ORDER BY td.day
        ", $params), [], $failedSegments);

        $topPurchasedProducts = $this->safeQuery(
            'topPurchasedProducts',
            fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(p.descripcion, 'Sin producto') AS product,
                SUM(ppds.qty_received) AS quantity,
                SUM(ppds.total_amount) AS total
            FROM purchase_product_daily_summary ppds
            LEFT JOIN producto p ON ppds.product_id = p.producto_id
            WHERE ppds.branch_id = :branchId
              AND ppds.summary_date >= :last30days
            GROUP BY ppds.product_id, p.descripcion
            ORDER BY total DESC
            LIMIT 5
        ", $params),
            [],
            $failedSegments
        );

        // Replaced INNER JOIN time_dimension with CONVERT_TZ by a direct UTC
        // range on issued_at — fully covered by idx_sales_branch_issued.
        $topCustomers = $this->safeQuery('topCustomers', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(c.razon_social, 'Cliente') AS customer,
                COUNT(*) AS invoices,
                SUM(s.grand_total) AS total
            FROM sales s
                LEFT JOIN cliente c ON s.customer_id = c.id
            WHERE s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
              AND s.branch_id = :branchId
              AND s.issued_at >= :last30daysUtc
              {$receiptFilter}
            GROUP BY c.id, c.razon_social
            ORDER BY total DESC
            LIMIT 5
        ", $params), [], $failedSegments);

        // Replaced INNER JOIN time_dimension + CONVERT_TZ on issue_date with a
        // direct UTC range — lets the existing index on tax_document be used.
        $voucherTypeStats = $this->safeQuery('voucherTypes', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(vt.descripcion, 'Sin tipo') AS voucher_type,
                COUNT(*) AS count,
                SUM(i.total) AS total
            FROM tax_document i
                INNER JOIN sales s ON (
                    (s.legacy_movimiento_id IS NOT NULL AND i.movimiento_id = s.legacy_movimiento_id)
                    OR
                    (i.sale_id IS NOT NULL AND i.sale_id = s.id)
                )
                    AND s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
                    AND s.branch_id = :branchId
                LEFT JOIN tipo_comprobante vt ON i.document_type = vt.codigo
            WHERE i.issue_date >= :monthStartDate
              AND i.issue_date < :monthEndDate
              AND i.document_type IN ('01', '03')
            GROUP BY vt.codigo, vt.descripcion
            ORDER BY total DESC
        ", $params), [], $failedSegments);

        $lowStockProducts = $this->safeQuery('lowStockProducts', fn () => $this->connection->fetchAllAssociative("
            SELECT
                p.descripcion AS product,
                p.producto_id AS product_id,
                si.qty_on_hand  AS current_stock,
                COALESCE(p.stock_minimo, 0) AS min_stock,
                COALESCE(p.precio, 0) AS avg_price
            FROM stock_items si
                INNER JOIN producto p  ON si.product_id  = p.producto_id
                INNER JOIN almacen  a  ON si.warehouse_id = a.almacen_id
            WHERE a.local_id = :branchId
              AND p.stock_minimo IS NOT NULL
              AND p.stock_minimo > 0
              AND si.qty_on_hand <= p.stock_minimo
            ORDER BY (si.qty_on_hand / p.stock_minimo) ASC
            LIMIT 10
        ", $params), [], $failedSegments);

        $refunds = $this->safeQuery('refunds', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN bds.refunds_total ELSE 0 END) AS refunds_today,
                SUM(
                    CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN bds.refunds_total ELSE 0 END
                ) AS refunds_month
            FROM branch_daily_summary bds
                INNER JOIN time_dimension td ON td.date = bds.summary_date
            WHERE bds.branch_id = :branchId
        ", $params) ?: [], [], $failedSegments);

        $payload = $this->emptyOverviewPayload();
        $summaryData = [
            'salesToday' => (float) ($summary['sales_today'] ?? 0),
            'salesYesterday' => (float) ($summary['sales_yesterday'] ?? 0),
            'salesWeek' => (float) ($summary['sales_week'] ?? 0),
            'salesThisMonth' => (float) ($summary['sales_month'] ?? 0),
            'salesLastMonth' => (float) ($summary['sales_previous_month'] ?? 0),
            'invoicesToday' => (int) ($summary['invoices_today'] ?? 0),
            'invoicesThisMonth' => (int) ($summary['invoices_month'] ?? 0),
            'customersThisMonth' => (int) ($customers['customers_month'] ?? 0),
            'customersWeek' => (int) ($customers['customers_week'] ?? 0),
            'refundsToday' => (float) ($refunds['refunds_today'] ?? 0),
            'refundsThisMonth' => (float) ($refunds['refunds_month'] ?? 0),
        ];

        $summaryData['avgTicket'] = $summaryData['invoicesThisMonth'] > 0
            ? $summaryData['salesThisMonth'] / $summaryData['invoicesThisMonth']
            : 0.0;

        $summaryData['avgTicketToday'] = $summaryData['invoicesToday'] > 0
            ? $summaryData['salesToday'] / $summaryData['invoicesToday']
            : 0.0;

        $summaryData['monthGrowth'] = $summaryData['salesLastMonth'] > 0
            ? (($summaryData['salesThisMonth'] - $summaryData['salesLastMonth']) / $summaryData['salesLastMonth']) * 100
            : null;

        $summaryData['dailyGrowth'] = $summaryData['salesYesterday'] > 0
            ? (($summaryData['salesToday'] - $summaryData['salesYesterday']) / $summaryData['salesYesterday']) * 100
            : null;

        $summaryData['netSalesToday'] = max($summaryData['salesToday'] - $summaryData['refundsToday'], 0);
        $summaryData['netSalesThisMonth'] = max($summaryData['salesThisMonth'] - $summaryData['refundsThisMonth'], 0);
        $payload['summary'] = $summaryData;
        $purchaseSummaryData = [
            'purchasesToday' => (float) ($purchaseSummary['purchases_today'] ?? 0),
            'purchasesWeek' => (float) ($purchaseSummary['purchases_week'] ?? 0),
            'purchasesThisMonth' => (float) ($purchaseSummary['purchases_month'] ?? 0),
            'purchasesLastMonth' => (float) ($purchaseSummary['purchases_previous_month'] ?? 0),
            'receiptsToday' => (int) ($purchaseSummary['receipts_today'] ?? 0),
            'receiptsThisMonth' => (int) ($purchaseSummary['receipts_month'] ?? 0),
        ];
        $purchaseSummaryData['monthGrowth'] = $purchaseSummaryData['purchasesLastMonth'] > 0
            ? (($purchaseSummaryData['purchasesThisMonth'] - $purchaseSummaryData['purchasesLastMonth'])
                / $purchaseSummaryData['purchasesLastMonth']) * 100
            : null;
        $payload['purchasesSummary'] = $purchaseSummaryData;

        $paymentTrendPivot = [];
        $methods = [];
        foreach ($paymentMethodTrendRaw as $row) {
            $methods[$row['method']] = true;
            $key = $row['label'];
            if (!isset($paymentTrendPivot[$key])) {
                $paymentTrendPivot[$key] = [
                    'label' => $row['label'],
                    'date' => $row['date_value'],
                ];
            }

            $paymentTrendPivot[$key][$row['method']] = (float) $row['total'];
        }

        $payload['salesByDay'] = array_map(static fn (array $row): array => [
                'day' => (int) $row['day'],
                'label' => $row['day_name'],
                'total' => (float) $row['total'],
            ], $salesByDay);
        $payload['salesByMonth'] = array_map(static fn (array $row): array => [
                'label' => $row['label'],
                'total' => (float) $row['total'],
            ], $salesByMonth);
        $payload['paymentMethods'] = array_map(static fn (array $row): array => [
                'method' => $row['method'],
                'total' => (float) $row['total'],
            ], $paymentMethodShare);
        $payload['paymentMethodTrend'] = [
            'methods' => array_keys($methods),
            'points' => array_values($paymentTrendPivot),
        ];
        $payload['topProducts'] = array_map(static fn (array $row): array => [
                'product' => $row['product'],
                'quantity' => (float) $row['quantity'],
                'total' => (float) $row['total'],
            ], $topProducts);
        $payload['purchasesByDay'] = array_map(static fn (array $row): array => [
            'day' => (int) $row['day'],
            'label' => $row['day_name'],
            'total' => (float) $row['total'],
        ], $purchasesByDay);
        $payload['topPurchasedProducts'] = array_map(static fn (array $row): array => [
            'product' => $row['product'],
            'quantity' => (float) $row['quantity'],
            'total' => (float) $row['total'],
        ], $topPurchasedProducts);
        $payload['topCustomers'] = array_map(static fn (array $row): array => [
                'customer' => $row['customer'],
                'invoices' => (int) $row['invoices'],
                'total' => (float) $row['total'],
            ], $topCustomers);
        $payload['voucherTypes'] = array_map(static fn (array $row): array => [
                'type' => $row['voucher_type'],
                'count' => (int) $row['count'],
                'total' => (float) $row['total'],
            ], $voucherTypeStats);
        $payload['lowStockProducts'] = array_map(
            static function (array $row): array {
                $fallbackMin = $row['min_stock'] > 0 ? $row['min_stock'] : 5;
                return [
                    'product' => $row['product'],
                    'productId' => $row['product_id'] ?? null,
                    'currentStock' => (float) $row['current_stock'],
                    'minStock' => (float) $row['min_stock'],
                    'avgPrice' => (float) $row['avg_price'],
                    'stockPercentage' => $fallbackMin > 0
                        ? round(($row['current_stock'] / $fallbackMin) * 100, 1)
                        : 0,
                ];
            },
            $lowStockProducts
        );

        $recentSalesToday = $this->safeQuery('recentSalesToday', fn () => $this->connection->fetchAllAssociative("
            SELECT
                s.id AS id,
                DATE_FORMAT(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima'), '%H:%i') AS issued_at,
                COALESCE(pm.name, 'Sin método') AS payment_method,
                s.grand_total AS total,
                s.series AS series,
                s.number AS number
            FROM sales s
                LEFT JOIN sale_payments sp ON sp.sale_id = s.id
                LEFT JOIN payment_method pm ON sp.payment_method_id = pm.id
            WHERE s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
              AND s.branch_id = :branchId
              AND s.issued_at >= :todayStartUtc
              AND s.issued_at < :todayEndUtc
              {$receiptFilter}
            GROUP BY s.id, issued_at, payment_method, total
            ORDER BY s.issued_at DESC
            LIMIT 10
        ", $params), [], $failedSegments);
        $payload['recentSalesToday'] = array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'issuedAt' => $row['issued_at'],
            'paymentMethod' => $row['payment_method'],
            'total' => (float) $row['total'],
            'series' => $row['series'] ?? null,
            'number' => $row['number'] ?? null,
        ], $recentSalesToday);

        $payload['meta'] = $this->buildMeta('full', $failedSegments);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLiteOverview(string $branchId): array
    {
        $ctx = $this->buildQueryContext($branchId);
        $params = $ctx['params'];
        $receiptFilter = $ctx['receiptFilterSales'];
        $failedSegments = [];
        $payload = $this->emptyLitePayload();

        $summary = $this->safeQuery('summaryLite', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN bds.net_sales ELSE 0 END) AS sales_today,
                SUM(CASE WHEN td.date = :yesterday THEN bds.net_sales ELSE 0 END) AS sales_yesterday,
                SUM(CASE WHEN td.date >= :last7days THEN bds.net_sales ELSE 0 END) AS sales_week,
                SUM(CASE WHEN td.date = :today THEN bds.sales_count ELSE 0 END) AS invoices_today
            FROM branch_daily_summary bds
                INNER JOIN time_dimension td ON td.date = bds.summary_date
            WHERE bds.branch_id = :branchId
        ", $params) ?: [], [], $failedSegments);

        $refunds = $this->safeQuery('refundsLite', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN bds.refunds_total ELSE 0 END) AS refunds_today
            FROM branch_daily_summary bds
                INNER JOIN time_dimension td ON td.date = bds.summary_date
            WHERE bds.branch_id = :branchId
        ", $params) ?: [], [], $failedSegments);

        $paymentMethodsToday = $this->safeQuery('paymentMethodsToday', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(pm.name, 'Sin método') AS method,
                COALESCE(SUM(pms.amount_total), 0) AS total
            FROM payment_method_daily_summary pms
                LEFT JOIN payment_method pm ON pms.payment_method_id = pm.id
            WHERE pms.branch_id = :branchId
              AND pms.summary_date = :today
            GROUP BY pm.id, pm.name
            ORDER BY total DESC
            LIMIT 5
        ", $params), [], $failedSegments);

        // Replaced INNER JOIN time_dimension + CONVERT_TZ with a direct UTC
        // range on issued_at — fully covered by idx_sales_branch_issued.
        $hourlySalesToday = $this->safeQuery('hourlySalesToday', fn () => $this->connection->fetchAllAssociative("
            SELECT
                DATE_FORMAT(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima'), '%H:00') AS hour_label,
                COALESCE(SUM(s.grand_total), 0) AS total
            FROM sales s
            WHERE s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
              AND s.branch_id = :branchId
              AND s.issued_at >= :todayStartUtc
              AND s.issued_at < :todayEndUtc
              {$receiptFilter}
            GROUP BY hour_label
            ORDER BY hour_label
        ", $params), [], $failedSegments);

        // Same fix for recentSalesToday.
        $recentSalesToday = $this->safeQuery('recentSalesToday', fn () => $this->connection->fetchAllAssociative("
            SELECT
                s.id AS id,
                DATE_FORMAT(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima'), '%H:%i') AS issued_at,
                COALESCE(pm.name, 'Sin método') AS payment_method,
                s.grand_total AS total,
                s.series AS series,
                s.number AS number
            FROM sales s
                LEFT JOIN sale_payments sp ON sp.sale_id = s.id
                LEFT JOIN payment_method pm ON sp.payment_method_id = pm.id
            WHERE s.status IN ('COMPLETED', 'PARTIALLY_RETURNED', 'RETURNED')
              AND s.branch_id = :branchId
              AND s.issued_at >= :todayStartUtc
              AND s.issued_at < :todayEndUtc
              {$receiptFilter}
            GROUP BY s.id, issued_at, payment_method, total
            ORDER BY s.issued_at DESC
            LIMIT 10
        ", $params), [], $failedSegments);

        $salesToday = (float) ($summary['sales_today'] ?? 0);
        $salesYesterday = (float) ($summary['sales_yesterday'] ?? 0);
        $salesWeek = (float) ($summary['sales_week'] ?? 0);
        $invoicesToday = (int) ($summary['invoices_today'] ?? 0);
        $refundsToday = (float) ($refunds['refunds_today'] ?? 0);
        $dailyGrowth = $salesYesterday > 0
            ? (($salesToday - $salesYesterday) / $salesYesterday) * 100
            : null;

        $payload['summary'] = [
            'salesToday' => $salesToday,
            'salesYesterday' => $salesYesterday,
            'salesWeek' => $salesWeek,
            'dailyGrowth' => $dailyGrowth,
            'invoicesToday' => $invoicesToday,
            'avgTicketToday' => $invoicesToday > 0 ? $salesToday / $invoicesToday : 0.0,
            'refundsToday' => $refundsToday,
            'netSalesToday' => max($salesToday - $refundsToday, 0),
        ];
        $payload['paymentMethodsToday'] = array_map(static fn (array $row): array => [
            'method' => $row['method'],
            'total' => (float) $row['total'],
        ], $paymentMethodsToday);
        $payload['hourlySalesToday'] = array_map(static fn (array $row): array => [
            'hour' => $row['hour_label'],
            'total' => (float) $row['total'],
        ], $hourlySalesToday);
        $payload['recentSalesToday'] = array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'issuedAt' => $row['issued_at'],
            'paymentMethod' => $row['payment_method'],
            'total' => (float) $row['total'],
            'series' => $row['series'] ?? null,
            'number' => $row['number'] ?? null,
        ], $recentSalesToday);
        $payload['meta'] = $this->buildMeta('lite', $failedSegments);

        return $payload;
    }

    // -------------------------------------------------------------------------
    // Query context
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function buildQueryContext(string $branchId): array
    {
        $tz = new DateTimeZone('America/Lima');
        $today = new DateTimeImmutable('now', $tz);
        $currentYear = (int) $today->format('Y');
        $currentMonth = (int) $today->format('n');
        $previousMonthDate = $today->modify('first day of last month');
        $previousYear = (int) $previousMonthDate->format('Y');
        $previousMonth = (int) $previousMonthDate->format('n');
        $yesterday = $today->modify('-1 day');
        $lastWeekStart = $today->modify('-7 days');

        // UTC boundaries for queries that filter directly on issued_at / issue_date.
        $utc = new DateTimeZone('UTC');
        $monthStart = new DateTimeImmutable($today->format('Y-m-01 00:00:00'), $tz);
        $monthEnd = $monthStart->modify('+1 month');
        $todayStart = new DateTimeImmutable($today->format('Y-m-d 00:00:00'), $tz);
        $todayEnd = $todayStart->modify('+1 day');

        return [
            'params' => [
                'branchId' => $branchId,
                'today' => $today->format('Y-m-d'),
                'yesterday' => $yesterday->format('Y-m-d'),
                'currentYear' => $currentYear,
                'currentMonth' => $currentMonth,
                'previousYear' => $previousYear,
                'previousMonth' => $previousMonth,
                'last30days' => $today->modify('-30 days')->format('Y-m-d'),
                'last7days' => $lastWeekStart->format('Y-m-d'),
                'monthsBackDate' => $today->modify('-11 months')->format('Y-m-d'),
                // UTC equivalents for sargable range filters on issued_at (DATETIME)
                'monthStartUtc' => $monthStart->setTimezone($utc)->format('Y-m-d H:i:s'),
                'monthEndUtc' => $monthEnd->setTimezone($utc)->format('Y-m-d H:i:s'),
                // Lima DATE equivalents for issue_date (DATE column, no tz conversion needed)
                'monthStartDate' => $monthStart->format('Y-m-d'),
                'monthEndDate' => $monthEnd->format('Y-m-d'),
                'last30daysUtc' => $today->modify('-30 days')->setTimezone($utc)->format('Y-m-d H:i:s'),
                'weekStartUtc' => $lastWeekStart->setTimezone($utc)->format('Y-m-d H:i:s'),
                'todayStartUtc' => $todayStart->setTimezone($utc)->format('Y-m-d H:i:s'),
                'todayEndUtc' => $todayEnd->setTimezone($utc)->format('Y-m-d H:i:s'),
            ],
            'receiptFilterSales' => $this->receiptFilter('s'),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function emptyOverviewPayload(): array
    {
        return [
            'summary' => [
                'salesToday' => 0.0,
                'salesYesterday' => 0.0,
                'salesWeek' => 0.0,
                'salesThisMonth' => 0.0,
                'salesLastMonth' => 0.0,
                'invoicesToday' => 0,
                'invoicesThisMonth' => 0,
                'customersThisMonth' => 0,
                'customersWeek' => 0,
                'avgTicket' => 0.0,
                'avgTicketToday' => 0.0,
                'monthGrowth' => null,
                'dailyGrowth' => null,
                'refundsToday' => 0.0,
                'refundsThisMonth' => 0.0,
                'netSalesToday' => 0.0,
                'netSalesThisMonth' => 0.0,
            ],
            'salesByDay' => [],
            'salesByMonth' => [],
            'paymentMethods' => [],
            'paymentMethodTrend' => [
                'methods' => [],
                'points' => [],
            ],
            'topProducts' => [],
            'purchasesSummary' => [
                'purchasesToday' => 0.0,
                'purchasesWeek' => 0.0,
                'purchasesThisMonth' => 0.0,
                'purchasesLastMonth' => 0.0,
                'receiptsToday' => 0,
                'receiptsThisMonth' => 0,
                'monthGrowth' => null,
            ],
            'purchasesByDay' => [],
            'topPurchasedProducts' => [],
            'topCustomers' => [],
            'voucherTypes' => [],
            'lowStockProducts' => [],
            'recentSalesToday' => [],
            'meta' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLitePayload(): array
    {
        return [
            'summary' => [
                'salesToday' => 0.0,
                'salesYesterday' => 0.0,
                'salesWeek' => 0.0,
                'dailyGrowth' => null,
                'invoicesToday' => 0,
                'avgTicketToday' => 0.0,
                'refundsToday' => 0.0,
                'netSalesToday' => 0.0,
            ],
            'paymentMethodsToday' => [],
            'hourlySalesToday' => [],
            'recentSalesToday' => [],
            'meta' => [],
        ];
    }

    /**
     * @param array<int, string> $failedSegments
     * @return array<string, mixed>
     */
    private function buildMeta(string $mode, array $failedSegments): array
    {
        return [
            'mode' => $mode,
            'partial' => count($failedSegments) > 0,
            'failedSegments' => $failedSegments,
            'generatedAt' => (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<int, string> $failedSegments
     */
    private function safeQuery(string $segment, callable $resolver, mixed $fallback, array &$failedSegments): mixed
    {
        try {
            return $resolver();
        } catch (\Throwable) {
            $failedSegments[] = $segment;
            return $fallback;
        }
    }

    private function receiptFilter(string $salesAlias = 's'): string
    {
        if ($this->sunatState->isReceiptRequired()) {
            return 'AND EXISTS (SELECT 1 FROM tax_document td2 '
                . 'WHERE ('
                . "({$salesAlias}.legacy_movimiento_id IS NOT NULL AND td2.movimiento_id = {$salesAlias}.legacy_movimiento_id)"
                . ' OR '
                . "(td2.sale_id IS NOT NULL AND td2.sale_id = {$salesAlias}.id)"
                . ") AND td2.document_type IN ('01','03'))";
        }

        return '';
    }
}
