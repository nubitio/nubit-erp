<?php

namespace App\Dashboard\Infrastructure;

use App\Dashboard\Domain\DashboardRepositoryInterface;
use Doctrine\DBAL\Connection;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
    )
    {
    }

    public function getDailySales($localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_dia (?)', [$localId]);
    }

    public function getWeeklySales($localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_semana (?)', [$localId]);
    }

    public function getMonthlySales($localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_mes (?)', [$localId]);
    }

    public function getMonthlyInvoicing($localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_monthly_invoicing');
    }

    public function getProductRotation($localId, $lapse): array
    {
        return $this->connection->fetchAllAssociative('CALL sp_bi_product_rotation (?, ?)', [$localId, $lapse]);
    }
}