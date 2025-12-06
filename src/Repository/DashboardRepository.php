<?php

namespace App\Repository;

use App\Dashboard\Domain\DashboardRepositoryInterface;
use Doctrine\DBAL\Connection;

readonly class DashboardRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Returns a consolidated dataset for the dashboard with KPIs and chart series.
     */
    public function getOverview(string $branchId): array
    {
        $today = new \DateTimeImmutable('now', new \DateTimeZone('America/Lima'));
        $currentYear = (int) $today->format('Y');
        $currentMonth = (int) $today->format('n');
        $previousMonthDate = $today->modify('first day of last month');
        $previousYear = (int) $previousMonthDate->format('Y');
        $previousMonth = (int) $previousMonthDate->format('n');
        $yesterday = $today->modify('-1 day');
        $lastWeekStart = $today->modify('-7 days');

        $params = [
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
        ];

        $summary = $this->connection->fetchAssociative("
            SELECT
                SUM(CASE WHEN td.date = :today THEN i.total ELSE 0 END) AS sales_today,
                SUM(CASE WHEN td.date = :yesterday THEN i.total ELSE 0 END) AS sales_yesterday,
                SUM(CASE WHEN td.date >= :last7days THEN i.total ELSE 0 END) AS sales_week,
                SUM(CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN i.total ELSE 0 END) AS sales_month,
                SUM(CASE WHEN td.year = :previousYear AND td.month = :previousMonth THEN i.total ELSE 0 END) AS sales_previous_month,
                SUM(CASE WHEN td.date = :today THEN 1 ELSE 0 END) AS invoices_today,
                SUM(CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN 1 ELSE 0 END) AS invoices_month,
                COUNT(DISTINCT CASE WHEN td.year = :currentYear AND td.month = :currentMonth THEN i.cliente_id END) AS customers_month,
                COUNT(DISTINCT CASE WHEN td.date >= :last7days THEN i.cliente_id END) AS customers_week
            FROM comprobante i
                INNER JOIN movimiento m ON i.movimiento_id = m.movimiento_id
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
            WHERE m.estado = 1
              AND m.tipo_id = 2
              AND a.local_id = :branchId
        ", $params) ?: [];

        $salesByDay = $this->connection->fetchAllAssociative("
            SELECT
                td.day AS day,
                td.day_name AS day_name,
                COALESCE(SUM(i.total), 0) AS total
            FROM time_dimension td
            LEFT JOIN comprobante i
                ON td.date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
            LEFT JOIN movimiento m ON i.movimiento_id = m.movimiento_id AND m.estado = 1 AND m.tipo_id = 2
            LEFT JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
            WHERE td.year = :currentYear
              AND td.month = :currentMonth
            GROUP BY td.id
            ORDER BY td.day
        ", $params);

        $salesByMonth = $this->connection->fetchAllAssociative("
            SELECT
                CONCAT(LEFT(td.month_name, 3), ' ', td.year) AS label,
                td.year,
                td.month,
                COALESCE(SUM(i.total), 0) AS total
            FROM time_dimension td
            LEFT JOIN comprobante i
                ON td.date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
            LEFT JOIN movimiento m ON i.movimiento_id = m.movimiento_id AND m.estado = 1 AND m.tipo_id = 2
            LEFT JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
            WHERE td.date >= :monthsBackDate
            GROUP BY td.year, td.month
            ORDER BY td.year, td.month
        ", $params);

        $paymentMethodShare = $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(pm.name, 'Sin método') AS method,
                COALESCE(SUM(i.total), 0) AS total
            FROM comprobante i
                INNER JOIN movimiento m ON i.movimiento_id = m.movimiento_id AND m.estado = 1 AND m.tipo_id = 2
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN payment_method pm ON m.tipo_pago = pm.id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
            WHERE td.date >= :last30days
            GROUP BY pm.id, pm.name
            ORDER BY total DESC
        ", $params);

        $topProducts = $this->connection->fetchAllAssociative("
            SELECT
                p.descripcion AS product,
                SUM(md.cantidad) AS quantity,
                SUM(md.precio_total) AS total
            FROM movimiento_detalle md
                INNER JOIN movimiento m ON md.movimiento_id = m.movimiento_id AND m.estado = 1 AND m.tipo_id = 2
                INNER JOIN comprobante i ON i.movimiento_id = m.movimiento_id
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                INNER JOIN producto p ON md.producto_id = p.producto_id
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
            WHERE td.date >= :last30days
            GROUP BY p.producto_id, p.descripcion
            ORDER BY total DESC
            LIMIT 5
        ", $params);

        $topCustomers = $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(c.razon_social, 'Cliente') AS customer,
                COUNT(*) AS invoices,
                SUM(i.total) AS total
            FROM comprobante i
                INNER JOIN movimiento m ON i.movimiento_id = m.movimiento_id AND m.estado = 1 AND m.tipo_id = 2
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN cliente c ON i.cliente_id = c.ruc
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
            WHERE td.date >= :last30days
            GROUP BY c.ruc, c.razon_social
            ORDER BY total DESC
            LIMIT 5
        ", $params);

        $voucherTypeStats = $this->connection->fetchAllAssociative("
            SELECT
                COALESCE(vt.descripcion, 'Sin tipo') AS voucher_type,
                COUNT(*) AS count,
                SUM(i.total) AS total
            FROM comprobante i
                INNER JOIN movimiento m ON i.movimiento_id = m.movimiento_id AND m.estado = 1 AND m.tipo_id = 2
                INNER JOIN almacen a ON m.almacen_id = a.almacen_id AND a.local_id = :branchId
                LEFT JOIN tipo_comprobante vt ON i.tipo_comprobante_id = vt.codigo
                INNER JOIN time_dimension td ON td.date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
            WHERE td.year = :currentYear AND td.month = :currentMonth
            GROUP BY vt.codigo, vt.descripcion
            ORDER BY total DESC
        ", $params);

        $lowStockProducts = $this->connection->fetchAllAssociative("
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
        ", $params);

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

        return [
            'summary' => $summaryData,
            'salesByDay' => array_map(static function (array $row): array {
                return [
                    'day' => (int) $row['day'],
                    'label' => $row['day_name'],
                    'total' => (float) $row['total'],
                ];
            }, $salesByDay),
            'salesByMonth' => array_map(static function (array $row): array {
                return [
                    'label' => $row['label'],
                    'total' => (float) $row['total'],
                ];
            }, $salesByMonth),
            'paymentMethods' => array_map(static function (array $row): array {
                return [
                    'method' => $row['method'],
                    'total' => (float) $row['total'],
                ];
            }, $paymentMethodShare),
            'topProducts' => array_map(static function (array $row): array {
                return [
                    'product' => $row['product'],
                    'quantity' => (float) $row['quantity'],
                    'total' => (float) $row['total'],
                ];
            }, $topProducts),
            'topCustomers' => array_map(static function (array $row): array {
                return [
                    'customer' => $row['customer'],
                    'invoices' => (int) $row['invoices'],
                    'total' => (float) $row['total'],
                ];
            }, $topCustomers),
            'voucherTypes' => array_map(static function (array $row): array {
                return [
                    'type' => $row['voucher_type'],
                    'count' => (int) $row['count'],
                    'total' => (float) $row['total'],
                ];
            }, $voucherTypeStats),
            'lowStockProducts' => array_map(static function (array $row): array {
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
            }, $lowStockProducts),
        ];
    }

    public function getDailySales(string $localId): array
    {
        $sql = "
        SELECT
            td.day AS dia,
            SUM(IFNULL(v.p_venta, 0)) AS venta
        FROM time_dimension td
        LEFT JOIN vw_descuento v ON td.date = DATE(CONVERT_TZ(v.fecha, 'UTC', 'America/Lima'))
            AND v.estado = 1
            AND v.local_id = ?
            AND td.month = MONTH(CONVERT_TZ(CURDATE(), 'UTC', 'America/Lima'))
            AND td.year = YEAR(CONVERT_TZ(CURDATE(), 'UTC', 'America/Lima'))
        GROUP BY td.day
        ORDER BY td.day
        ";

        return $this->connection->fetchAllAssociative($sql, [$localId]);
    }

    public function getWeeklySales(string $localId): array
    {
        $sql = "
        SELECT
            td.day_name AS dia,
            SUM(IFNULL(v.p_venta, 0)) AS venta
        FROM time_dimension td
        LEFT JOIN vw_descuento v ON td.date = DATE(CONVERT_TZ(v.fecha, 'UTC', 'America/Lima'))
            AND v.estado = 1
            AND v.local_id = ?
        WHERE YEARWEEK( td.date ) = YEARWEEK(CONVERT_TZ(CURDATE(), 'UTC', 'America/Lima' ) )
        GROUP BY td.date
        ORDER BY td.date
        ";

        return $this->connection->fetchAllAssociative($sql, [$localId]);
    }

    public function getMonthlySales(string $localId): array
    {
        $sql = "
        SELECT
            td.month_name AS mes,
            SUM(IFNULL(v.p_venta, 0)) AS venta
        FROM
            time_dimension td
            LEFT JOIN vw_descuento v ON td.date = DATE(CONVERT_TZ(v.fecha, 'UTC', 'America/Lima'))
            AND v.estado = 1
            AND v.local_id = ?
        WHERE
            td.date > DATE_SUB(CONVERT_TZ(CURDATE(), 'UTC', 'America/Lima'), INTERVAL 11 MONTH)
        GROUP BY
            td.year,
            td.month
        ORDER BY
            td.year,
            td.month
        ";

        return $this->connection->fetchAllAssociative($sql, [$localId]);
    }

    public function getMonthlyInvoicing(): array
    {
        $sql = "
        SELECT
            td.month_name AS `month`,
            COUNT(i.comprobante_id) AS total
        FROM
            time_dimension td
            LEFT JOIN comprobante i ON td.date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
        WHERE
            td.date > DATE_SUB(CONVERT_TZ(CURDATE(), 'UTC', 'America/Lima'), INTERVAL 11 MONTH)
        GROUP BY
            td.year,
            td.month
        ORDER BY
            td.year,
            td.month
        ";

        return $this->connection->fetchAllAssociative($sql);
    }
}
