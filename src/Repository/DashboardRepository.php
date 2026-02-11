<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

readonly class DashboardRepository
{
    public function __construct(
        private Connection $connection,
        private SettingsService $settings,
    ) {
    }

    public function getOverview(string $branchId): array
    {
        $ctx = $this->buildQueryContext($branchId);
        $params = $ctx['params'];
        $receiptFilter = $ctx['receiptFilter'];
        $failedSegments = [];

        $summary = $this->safeQuery('summary', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN m.total ELSE 0 END) AS sales_today,
                SUM(CASE WHEN td.date = :yesterday THEN m.total ELSE 0 END) AS sales_yesterday,
                SUM(CASE WHEN td.date >= :last7days THEN m.total ELSE 0 END) AS sales_week,
                SUM(
                    CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN m.total ELSE 0 END
                ) AS sales_month,
                SUM(
                    CASE WHEN td.year = :previousYear AND td.month = :previousMonth THEN m.total ELSE 0 END
                ) AS sales_previous_month,
                SUM(CASE WHEN td.date = :today THEN 1 ELSE 0 END) AS invoices_today,
                SUM(CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN 1 ELSE 0 END) AS invoices_month,
                COUNT(
                    DISTINCT CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN m.cliente_id END
                ) AS customers_month,
                COUNT(DISTINCT CASE WHEN td.date >= :last7days THEN m.cliente_id END) AS customers_week
            FROM movimiento m
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
            WHERE m.estado = 1
              AND m.tipo_id = 2
              AND a.local_id = :branchId
              {$receiptFilter}
        ", $params) ?: [], [], $failedSegments);

        $salesByDay = $this->safeQuery('salesByDay', fn () => $this->connection->fetchAllAssociative("
            SELECT
                td.day AS day,
                td.day_name AS day_name,
                COALESCE(SUM(m.total), 0) AS total
            FROM time_dimension td
            LEFT JOIN movimiento m
                ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
                AND m.estado = 1
                AND m.tipo_id = 2
            LEFT JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
            WHERE td.year = :currentYear
              AND td.month = :currentMonth
              AND (m.movimiento_id IS NULL OR a.local_id = :branchId)
              {$receiptFilter}
            GROUP BY td.id, td.day
            ORDER BY td.day
        ", $params), [], $failedSegments);

        $salesByMonth = $this->safeQuery('salesByMonth', fn () => $this->connection->fetchAllAssociative("
            SELECT
                CONCAT(LEFT(td.month_name, 3), ' ', td.year) AS label,
                td.year,
                td.month,
                COALESCE(SUM(m.total), 0) AS total
            FROM time_dimension td
            LEFT JOIN movimiento m
                ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
                AND m.estado = 1
                AND m.tipo_id = 2
            LEFT JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
            WHERE td.date >= :monthsBackDate
              AND td.date <= :today
              AND (m.movimiento_id IS NULL OR a.local_id = :branchId)
              {$receiptFilter}
            GROUP BY td.year, td.month
            ORDER BY td.year, td.month
        ", $params), [], $failedSegments);

        $paymentMethodShare = $this->safeQuery('paymentMethods', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(pm.name, 'Sin método') AS method,
                COALESCE(SUM(m.total), 0) AS total
            FROM movimiento m
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN payment_method pm ON m.tipo_pago = pm.id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
            WHERE m.estado = 1
              AND m.tipo_id = 2
              AND td.date >= :last30days
              {$receiptFilter}
            GROUP BY pm.id, pm.name
            ORDER BY total DESC
        ", $params), [], $failedSegments);

        $paymentMethodTrendRaw = $this->safeQuery(
            'paymentMethodTrend',
            fn () => $this->connection->fetchAllAssociative("
                SELECT
                    td.date AS date_value,
                    DATE_FORMAT(td.date, '%d %b') AS label,
                    COALESCE(pm.name, 'Sin método') AS method,
                    COALESCE(SUM(m.total), 0) AS total
                FROM time_dimension td
                LEFT JOIN movimiento m
                    ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
                    AND m.estado = 1
                    AND m.tipo_id = 2
                LEFT JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN payment_method pm ON m.tipo_pago = pm.id
                WHERE td.date >= :last30days
                  AND td.date <= :today
                  AND (m.movimiento_id IS NULL OR a.local_id = :branchId)
                  {$receiptFilter}
                GROUP BY td.date, pm.id, pm.name
                ORDER BY td.date
            ", $params),
            [],
            $failedSegments
        );

        $topProducts = $this->safeQuery('topProducts', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(p.descripcion, 'Sin producto') AS product,
                SUM(md.cantidad) AS quantity,
                SUM(md.precio_total) AS total
            FROM movimiento_detalle md
                INNER JOIN movimiento m ON m.movimiento_id = md.movimiento_id AND m.estado = 1 AND m.tipo_id = 2
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN producto p ON md.producto_id = p.producto_id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
            WHERE td.date >= :last30days
              {$receiptFilter}
            GROUP BY md.producto_id, p.descripcion
            ORDER BY total DESC
            LIMIT 5
        ", $params), [], $failedSegments);

        $topCustomers = $this->safeQuery('topCustomers', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(c.razon_social, 'Cliente') AS customer,
                COUNT(*) AS invoices,
                SUM(m.total) AS total
            FROM movimiento m
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN cliente c ON m.cliente_id = c.id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
            WHERE m.estado = 1
              AND m.tipo_id = 2
              AND td.date >= :last30days
              {$receiptFilter}
            GROUP BY c.id, c.razon_social
            ORDER BY total DESC
            LIMIT 5
        ", $params), [], $failedSegments);

        $voucherTypeStats = $this->safeQuery('voucherTypes', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(vt.descripcion, 'Sin tipo') AS voucher_type,
                COUNT(*) AS count,
                SUM(i.total) AS total
            FROM tax_document i
                INNER JOIN movimiento m ON i.movimiento_id = m.movimiento_id AND m.estado = 1 AND m.tipo_id = 2
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN tipo_comprobante vt ON i.document_type = vt.codigo
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(i.issue_date, 'UTC', 'America/Lima'))
            WHERE td.year = :currentYear AND td.month = :currentMonth
              AND i.document_type IN ('01', '03')
            GROUP BY vt.codigo, vt.descripcion
            ORDER BY total DESC
        ", $params), [], $failedSegments);

        $lowStockProducts = $this->safeQuery('lowStockProducts', fn () => $this->connection->fetchAllAssociative("
            SELECT
                p.descripcion AS product,
                ps.stock AS current_stock,
                COALESCE(ps.min_stock, 0) AS min_stock,
                COALESCE(p.precio, 0) AS avg_price
            FROM product_stock ps
                INNER JOIN producto p ON ps.product_id = p.producto_id
                INNER JOIN almacen a ON ps.warehouse_id = a.almacen_id
            WHERE a.local_id = :branchId
              AND ps.stock <= COALESCE(NULLIF(ps.min_stock, 0), 5)
            ORDER BY (ps.stock / COALESCE(NULLIF(ps.min_stock, 0), 5)) ASC
            LIMIT 10
        ", $params), [], $failedSegments);

        $refunds = $this->safeQuery('refunds', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN n.total ELSE 0 END) AS refunds_today,
                SUM(
                    CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN n.total ELSE 0 END
                ) AS refunds_month
            FROM tax_document n
                INNER JOIN movimiento m ON n.movimiento_id = m.movimiento_id
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(n.issue_date, 'UTC', 'America/Lima'))
            WHERE n.document_type IN ('07', '08')
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
            'customersThisMonth' => (int) ($summary['customers_month'] ?? 0),
            'customersWeek' => (int) ($summary['customers_week'] ?? 0),
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
        $payload['meta'] = $this->buildMeta('full', $failedSegments);

        return $payload;
    }

    public function getLiteOverview(string $branchId): array
    {
        $ctx = $this->buildQueryContext($branchId);
        $params = $ctx['params'];
        $receiptFilter = $ctx['receiptFilter'];
        $failedSegments = [];
        $payload = $this->emptyLitePayload();

        $summary = $this->safeQuery('summaryLite', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN m.total ELSE 0 END) AS sales_today,
                SUM(CASE WHEN td.date = :today THEN 1 ELSE 0 END) AS invoices_today
            FROM movimiento m
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
            WHERE m.estado = 1
              AND m.tipo_id = 2
              AND a.local_id = :branchId
              {$receiptFilter}
        ", $params) ?: [], [], $failedSegments);

        $refunds = $this->safeQuery('refundsLite', fn () => $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN n.total ELSE 0 END) AS refunds_today
            FROM tax_document n
                INNER JOIN movimiento m ON n.movimiento_id = m.movimiento_id
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(n.issue_date, 'UTC', 'America/Lima'))
            WHERE n.document_type IN ('07', '08')
        ", $params) ?: [], [], $failedSegments);

        $paymentMethodsToday = $this->safeQuery('paymentMethodsToday', fn () => $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(pm.name, 'Sin método') AS method,
                COALESCE(SUM(m.total), 0) AS total
            FROM movimiento m
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN payment_method pm ON m.tipo_pago = pm.id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
            WHERE m.estado = 1
              AND m.tipo_id = 2
              AND td.date = :today
              {$receiptFilter}
            GROUP BY pm.id, pm.name
            ORDER BY total DESC
            LIMIT 5
        ", $params), [], $failedSegments);

        $hourlySalesToday = $this->safeQuery('hourlySalesToday', fn () => $this->connection->fetchAllAssociative("
            SELECT
                DATE_FORMAT(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'), '%H:00') AS hour_label,
                COALESCE(SUM(m.total), 0) AS total
            FROM movimiento m
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
            WHERE m.estado = 1
              AND m.tipo_id = 2
              AND td.date = :today
              {$receiptFilter}
            GROUP BY hour_label
            ORDER BY hour_label
        ", $params), [], $failedSegments);

        $recentSalesToday = $this->safeQuery('recentSalesToday', fn () => $this->connection->fetchAllAssociative("
            SELECT
                m.movimiento_id AS id,
                DATE_FORMAT(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'), '%H:%i') AS issued_at,
                COALESCE(pm.name, 'Sin método') AS payment_method,
                m.total AS total
            FROM movimiento m
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN payment_method pm ON m.tipo_pago = pm.id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(m.fecha, 'UTC', 'America/Lima'))
            WHERE m.estado = 1
              AND m.tipo_id = 2
              AND td.date = :today
              {$receiptFilter}
            ORDER BY m.fecha DESC
            LIMIT 10
        ", $params), [], $failedSegments);

        $salesToday = (float) ($summary['sales_today'] ?? 0);
        $invoicesToday = (int) ($summary['invoices_today'] ?? 0);
        $refundsToday = (float) ($refunds['refunds_today'] ?? 0);

        $payload['summary'] = [
            'salesToday' => $salesToday,
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
            'id' => (int) $row['id'],
            'issuedAt' => $row['issued_at'],
            'paymentMethod' => $row['payment_method'],
            'total' => (float) $row['total'],
        ], $recentSalesToday);
        $payload['meta'] = $this->buildMeta('lite', $failedSegments);

        return $payload;
    }

    private function buildQueryContext(string $branchId): array
    {
        $today = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
        $currentYear = (int) $today->format('Y');
        $currentMonth = (int) $today->format('n');
        $previousMonthDate = $today->modify('first day of last month');
        $previousYear = (int) $previousMonthDate->format('Y');
        $previousMonth = (int) $previousMonthDate->format('n');
        $yesterday = $today->modify('-1 day');
        $lastWeekStart = $today->modify('-7 days');

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
            ],
            'receiptFilter' => $this->receiptFilter(),
        ];
    }

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
            'topCustomers' => [],
            'voucherTypes' => [],
            'lowStockProducts' => [],
            'meta' => [],
        ];
    }

    private function emptyLitePayload(): array
    {
        return [
            'summary' => [
                'salesToday' => 0.0,
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

    private function buildMeta(string $mode, array $failedSegments): array
    {
        return [
            'mode' => $mode,
            'partial' => count($failedSegments) > 0,
            'failedSegments' => $failedSegments,
            'generatedAt' => (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format(DATE_ATOM),
        ];
    }

    private function safeQuery(string $segment, callable $resolver, mixed $fallback, array &$failedSegments): mixed
    {
        try {
            return $resolver();
        } catch (\Throwable) {
            $failedSegments[] = $segment;
            return $fallback;
        }
    }

    private function receiptFilter(): string
    {
        if ($this->settings->get(Constants::RECEIPT_REQUIRED)) {
            return 'AND EXISTS (SELECT 1 FROM tax_document td2 '
                . 'WHERE td2.movimiento_id = m.movimiento_id '
                . "AND td2.document_type IN ('01','03'))";
        }

        return '';
    }
}
