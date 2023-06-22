<?php

namespace App\Kardex\Infrastructure;

use App\Core\Infrastructure\Common\DataGridLoader;
use App\Kardex\Domain\KardexRepositoryInterface;
use Doctrine\DBAL\Connection;

readonly class KardexRepository implements KardexRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private DataGridLoader $dataGridLoader
    ) {
    }

    public function getAll(string $localId, ?string $warehouseId, ?string $categoryId, ?string $dateFrom, ?string $dateTo): array
    {
        if (empty($dateFrom) || empty($dateTo)) {
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-t');
        }

        $table = '
        select
            p.producto_id,
            p.codigo,
            p.descripcion as producto,
            sum(if(m.tipo_id = 1, md.cantidad, 0)) as ingresos,
            sum(if(m.tipo_id = 2, md.cantidad, 0)) as salidas,
            sum(if(m.tipo_id = 1, md.cantidad, 0)) - sum(if(m.tipo_id = 2, md.cantidad, 0)) as stock
        from producto p
        left join movimiento_detalle md on p.producto_id = md.producto_id
        left join movimiento m on md.movimiento_id = m.movimiento_id and m.estado = 1
        group by p.producto_id, p.codigo, p.descripcion';

        return $this->dataGridLoader->load("($table) t", 'producto');
    }

    public function getOne(?string $warehouseId, ?string $productId, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->connection->fetchAllAssociative('call sp_kardex (?,?,?,?)', [
            $warehouseId,
            $productId,
            $dateFrom ?? date('Y-m-01'),
            $dateTo ?? date('Y-m-t'),
        ]);
    }
}
