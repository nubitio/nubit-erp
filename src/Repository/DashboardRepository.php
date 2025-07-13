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

    public function getDailySales(string $localId): array
    {
        $sql = "
        SELECT
            td.day AS dia,
            SUM(IFNULL(v.p_venta, 0)) AS venta
        FROM time_dimension td
        LEFT JOIN vw_descuento v ON td.db_date = DATE(CONVERT_TZ(v.fecha, 'UTC', 'America/Lima'))
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
        LEFT JOIN vw_descuento v ON td.db_date = DATE(CONVERT_TZ(v.fecha, 'UTC', 'America/Lima'))
            AND v.estado = 1
            AND v.local_id = ?
        WHERE YEARWEEK( td.db_date ) = YEARWEEK(CONVERT_TZ(CURDATE(), 'UTC', 'America/Lima' ) )
        GROUP BY td.db_date
        ORDER BY td.db_date
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
            LEFT JOIN vw_descuento v ON td.db_date = DATE(CONVERT_TZ(v.fecha, 'UTC', 'America/Lima'))
            AND v.estado = 1
            AND v.local_id = ?
        WHERE
            td.db_date > DATE_SUB(CONVERT_TZ(CURDATE(), 'UTC', 'America/Lima'), INTERVAL 11 MONTH)
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
            LEFT JOIN comprobante i ON td.db_date = DATE(CONVERT_TZ(i.fecha, 'UTC', 'America/Lima'))
        WHERE
            td.db_date > DATE_SUB(CONVERT_TZ(CURDATE(), 'UTC', 'America/Lima'), INTERVAL 11 MONTH)
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
