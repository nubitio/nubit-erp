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

    #[\Override]
    public function getDailySales(string $localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_dia (?)', [$localId]);
    }

    #[\Override]
    public function getWeeklySales(string $localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_semana (?)', [$localId]);
    }

    #[\Override]
    public function getMonthlySales(string $localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_venta_por_mes (?)', [$localId]);
    }

    #[\Override]
    public function getMonthlyInvoicing(): array
    {
        return $this->connection->fetchAllAssociative('call sp_bi_monthly_invoicing');
    }

    #[\Override]
    public function getProductRotation(string $localId, string $lapse): array
    {
        return $this->connection->fetchAllAssociative('CALL sp_bi_product_rotation (?, ?)', [$localId, $lapse]);
    }
}
