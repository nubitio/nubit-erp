<?php

namespace App\Dashboard\Infrastructure;

use App\Dashboard\Domain\DashboardRepositoryInterface;
use Doctrine\DBAL\Connection;

readonly class DashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /** @inheritdoc  */
    public function getDailySales(string $localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_dia (?)', [$localId]);
    }

    /** @inheritdoc  */
    public function getWeeklySales(string $localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_semana (?)', [$localId]);
    }

    /** @inheritdoc  */
    public function getMonthlySales(string $localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_mes (?)', [$localId]);
    }

    /** @inheritdoc  */
    public function getMonthlyInvoicing(string $localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_monthly_invoicing');
    }

    /** @inheritdoc  */
    public function getProductRotation(string $localId, string $lapse): array
    {
        return $this->connection->fetchAllAssociative('CALL sp_bi_product_rotation (?, ?)', [$localId, $lapse]);
    }
}
