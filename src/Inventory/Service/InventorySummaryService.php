<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class InventorySummaryService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

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
    public function getSummary(?string $warehouseId = null): array
    {
        $warehouseId = $this->normalizeWarehouseId($warehouseId);

        return [
            'totalSkus' => $this->countDistinctSkus($warehouseId),
            'categoryCount' => $this->countDistinctCategories($warehouseId),
            'stockValueAtCost' => $this->sumStockValueAtCost($warehouseId),
            'lowStockCount' => $this->countLowStock($warehouseId),
            'outOfStockCount' => $this->countOutOfStock($warehouseId),
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
    private function countDistinctSkus(?string $warehouseId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(DISTINCT si.product_id)
            FROM stock_items si
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $warehouseId, 'si');

        return (int) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @throws Exception
     */
    private function countDistinctCategories(?string $warehouseId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(DISTINCT p.categoria_id)
            FROM stock_items si
            INNER JOIN producto p ON p.producto_id = si.product_id
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $warehouseId, 'si');

        return (int) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @throws Exception
     */
    private function sumStockValueAtCost(?string $warehouseId): string
    {
        $sql = <<<'SQL'
            SELECT COALESCE(SUM(si.qty_on_hand * COALESCE(p.precio_compra, 0)), 0)
            FROM stock_items si
            INNER JOIN producto p ON p.producto_id = si.product_id
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $warehouseId, 'si');

        $value = $this->connection->fetchOne($sql, $params);

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @throws Exception
     */
    private function countLowStock(?string $warehouseId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM stock_items si
            INNER JOIN producto p ON p.producto_id = si.product_id
            WHERE p.stock_minimo IS NOT NULL
              AND p.stock_minimo > 0
              AND si.qty_on_hand <= p.stock_minimo
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $warehouseId, 'si', hasWhere: true);

        return (int) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @throws Exception
     */
    private function countOutOfStock(?string $warehouseId): int
    {
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM stock_items si
            WHERE si.qty_on_hand <= 0
            SQL;

        [$sql, $params] = $this->applyWarehouseFilter($sql, $warehouseId, 'si', hasWhere: true);

        return (int) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function applyWarehouseFilter(
        string $sql,
        ?string $warehouseId,
        string $alias,
        bool $hasWhere = false,
    ): array {
        if ($warehouseId === null) {
            return [$sql, []];
        }

        $connector = $hasWhere ? ' AND ' : ' WHERE ';

        return [
            $sql . $connector . "{$alias}.warehouse_id = :warehouseId",
            ['warehouseId' => $warehouseId],
        ];
    }
}