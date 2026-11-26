<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class InventorySummaryService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @return array{
     *     totalSkus: int,
     *     categoryCount: int,
     *     stockValueAtCost: string,
     *     lowStockCount: int,
     *     outOfStockCount: int,
     *     warehouseId: string|null
     * }
     *
     * @throws Exception
     */
    public function getSummary(string $branchId, ?string $warehouseId = null): array
    {
        $warehouseId = $this->normalizeWarehouseId($warehouseId);

        return [
            'totalSkus' => $this->countDistinctSkus($branchId, $warehouseId),
            'categoryCount' => $this->countDistinctCategories($branchId, $warehouseId),
            'stockValueAtCost' => $this->sumStockValueAtCost($branchId, $warehouseId),
            'lowStockCount' => $this->countLowStock($branchId, $warehouseId),
            'outOfStockCount' => $this->countOutOfStock($branchId, $warehouseId),
            'warehouseId' => $warehouseId,
        ];
    }

    private function normalizeWarehouseId(?string $warehouseId): ?string
    {
        if ($warehouseId === null || $warehouseId === '') {
            return null;
        }

        return $warehouseId;
    }

    /**
     * @throws Exception
     */
    private function countDistinctSkus(string $branchId, ?string $warehouseId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(DISTINCT si.product_id)
            FROM stock_items si
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $branchId, $warehouseId);

        return (int) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @throws Exception
     */
    private function countDistinctCategories(string $branchId, ?string $warehouseId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(DISTINCT p.categoria_id)
            FROM stock_items si
            INNER JOIN producto p ON p.producto_id = si.product_id
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $branchId, $warehouseId);

        return (int) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @throws Exception
     */
    private function sumStockValueAtCost(string $branchId, ?string $warehouseId): string
    {
        $sql = <<<'SQL'
            SELECT COALESCE(SUM(si.qty_on_hand * COALESCE(p.precio_compra, 0)), 0)
            FROM stock_items si
            INNER JOIN producto p ON p.producto_id = si.product_id
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $branchId, $warehouseId);

        $value = $this->connection->fetchOne($sql, $params);

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @throws Exception
     */
    private function countLowStock(string $branchId, ?string $warehouseId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM stock_items si
            INNER JOIN producto p ON p.producto_id = si.product_id
            WHERE p.stock_minimo IS NOT NULL
              AND p.stock_minimo > 0
              AND si.qty_on_hand <= p.stock_minimo
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $branchId, $warehouseId, hasWhere: true);

        return (int) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @throws Exception
     */
    private function countOutOfStock(string $branchId, ?string $warehouseId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM stock_items si
            WHERE si.qty_on_hand <= 0
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $branchId, $warehouseId, hasWhere: true);

        return (int) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function applyWarehouseFilter(
        string $sql,
        string $branchId,
        ?string $warehouseId,
        bool $hasWhere = false,
    ): array {
        $connector = $hasWhere ? ' AND ' : ' WHERE ';
        $sql .= $connector . 'si.warehouse_id IN (SELECT almacen_id FROM almacen WHERE local_id = :branchId)';
        $params = ['branchId' => $branchId];

        if ($warehouseId !== null) {
            $sql .= ' AND si.warehouse_id = :warehouseId';
            $params['warehouseId'] = $warehouseId;
        }

        return [$sql, $params];
    }
}
