<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use Doctrine\DBAL\Connection;

final readonly class StockRebuilderJob
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Rebuild stock_items from stock_movements for a single warehouse/product slice.
     * Designed for batched tenant-safe rebuilds.
     */
    public function rebuild(?string $warehouseId = null, ?string $productId = null): int
    {
        $sql = <<<'SQL'
INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at)
SELECT
    sm.warehouse_id,
    sm.product_id,
    COALESCE(SUM(sm.qty_delta_base), 0) AS qty_on_hand,
    0 AS qty_reserved,
    NOW() AS updated_at
FROM stock_movements sm
WHERE (:warehouse_id IS NULL OR sm.warehouse_id = :warehouse_id)
  AND (:product_id IS NULL OR sm.product_id = :product_id)
GROUP BY sm.warehouse_id, sm.product_id
ON DUPLICATE KEY UPDATE
    qty_on_hand = VALUES(qty_on_hand),
    updated_at = VALUES(updated_at)
SQL;

        return $this->connection->executeStatement($sql, [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
        ]);
    }
}
