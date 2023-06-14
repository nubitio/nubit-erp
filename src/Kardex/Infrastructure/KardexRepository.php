<?php

namespace App\Kardex\Infrastructure;

use App\Core\Infrastructure\Common\DataGridLoader;
use App\Kardex\Domain\KardexRepositoryInterface;
use Doctrine\DBAL\Connection;

class KardexRepository implements KardexRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DataGridLoader $dataGridLoader
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(string $localId, ?string $warehouseId, ?string $categoryId, ?string $dateFrom, ?string $dateTo): array
    {
        if (empty($dateFrom) || empty($dateTo)) {
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-t');
        }

        $table = 'select producto_id, codigo, descripcion as producto from producto';

        return $this->dataGridLoader->load("($table) t", 'producto_id', 'ASC');
    }

    /** {@inheritdoc} */
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
