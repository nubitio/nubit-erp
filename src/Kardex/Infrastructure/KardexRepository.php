<?php

namespace App\Kardex\Infrastructure;

use App\Kardex\Domain\KardexRepositoryInterface;
use Doctrine\DBAL\Connection;

class KardexRepository implements KardexRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    )
    {
    }

    public function getAll(string $localId, ?string $warehouseId, ?string $categoryId, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->connection->fetchAllAssociative('call sp_reporte_movimientos_pos (?,?,?,?,?)', [
            $localId,
            $warehouseId,
            $categoryId,
            $dateFrom ?? date('Y-m-01'),
            $dateTo ?? date('Y-m-t')
        ]);
    }
}