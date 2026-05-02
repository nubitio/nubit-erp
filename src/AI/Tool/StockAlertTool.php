<?php

declare(strict_types=1);

namespace App\AI\Tool;

use DateTimeImmutable;
use App\AI\Context\BranchContextInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'stock_alert',
    description: 'Returns a list of products that are at or below their minimum stock level, '
        . 'sorted by urgency (fewest days to stockout first). '
        . 'Includes estimated days to stockout based on 30-day sales velocity.',
    method: 'stockAlert',
)]
final readonly class StockAlertTool
{
    private const int MAX_LIMIT = 100;

    public function __construct(
        private Connection $connection,
        private BranchContextInterface $branchContextService,
    ) {
    }

    /**
     * Returns products at or below minimum stock, sorted by urgency.
     *
     * @return array{alerts: list<array{product_id: string, product_name: string, sku: string|null, qty_on_hand: float, min_stock: float, days_to_stockout: float|null, unit: string}>, total_below_min: int}
     */
    public function stockAlert(int $limit = 20): array
    {
        $limit = min(max(1, $limit), self::MAX_LIMIT);
        $branchId = $this->branchContextService->getCurrentBranchId();

        // Build the branch filter JOIN clause
        $branchJoin = '';
        $branchParam = [];
        if ($branchId !== null) {
            $branchJoin = 'JOIN almacen a ON a.almacen_id = si.warehouse_id AND a.local_id = :branch_id';
            $branchParam = ['branch_id' => $branchId];
        } else {
            $branchJoin = 'JOIN almacen a ON a.almacen_id = si.warehouse_id';
        }

        // Get products below minimum stock with their unit name
        // Uses stock_items (V2 source of truth) joined to producto.stock_minimo
        $belowMinSql = <<<SQL
            SELECT
                p.producto_id           AS product_id,
                p.descripcion           AS product_name,
                p.sku                   AS sku,
                SUM(si.qty_on_hand)     AS qty_on_hand,
                MAX(p.stock_minimo)     AS min_stock,
                u.name                  AS unit
            FROM stock_items si
            {$branchJoin}
            JOIN producto p ON p.producto_id = si.product_id
            LEFT JOIN unidad u ON u.unidad_id = p.unidad_id
            WHERE p.stock_minimo IS NOT NULL AND p.stock_minimo > 0
            GROUP BY p.producto_id, p.descripcion, p.sku, u.name
            HAVING SUM(si.qty_on_hand) <= MAX(p.stock_minimo)
            SQL;

        /** @var list<array{product_id: string, product_name: string, sku: string|null, qty_on_hand: string, min_stock: string, unit: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative($belowMinSql, $branchParam);

        $totalBelowMin = count($rows);

        if ($totalBelowMin === 0) {
            return ['alerts' => [], 'total_below_min' => 0];
        }

        // Compute 30-day sales velocity for each product
        $productIds = array_map(static fn (array $r): string => $r['product_id'], $rows);
        $thirtyDaysAgo = new DateTimeImmutable('-30 days')->format('Y-m-d H:i:s');
        $now = new DateTimeImmutable()->format('Y-m-d H:i:s');

        $velocitySql = <<<SQL
            SELECT
                si.product_id,
                COALESCE(SUM(si.qty), 0) / 30.0 AS velocity
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            WHERE si.product_id IN (:product_ids)
              AND s.issued_at BETWEEN :from AND :to
              AND s.deleted_at IS NULL
            GROUP BY si.product_id
            SQL;

        /** @var list<array{product_id: string, velocity: string}> $velocityRows */
        $velocityRows = $this->connection->fetchAllAssociative(
            $velocitySql,
            [
                'product_ids' => $productIds,
                'from' => $thirtyDaysAgo,
                'to' => $now,
            ],
            ['product_ids' => ArrayParameterType::STRING],
        );

        $velocityMap = [];
        foreach ($velocityRows as $vr) {
            $velocityMap[(string) $vr['product_id']] = (float) $vr['velocity'];
        }

        // Build alert items with days_to_stockout
        $alerts = [];
        foreach ($rows as $row) {
            $pid = (string) $row['product_id'];
            $qtyOnHand = (float) $row['qty_on_hand'];
            $velocity = $velocityMap[$pid] ?? 0.0;

            $daysToStockout = null;
            if ($velocity > 0.0) {
                $daysToStockout = round($qtyOnHand / $velocity, 1);
            }

            $alerts[] = [
                'product_id' => $pid,
                'product_name' => (string) $row['product_name'],
                'sku' => $row['sku'] ?? null,
                'qty_on_hand' => $qtyOnHand,
                'min_stock' => (float) $row['min_stock'],
                'days_to_stockout' => $daysToStockout,
                'unit' => $row['unit'] ?? '',
            ];
        }

        // Sort: urgency ASC (nulls last)
        usort($alerts, static function (array $a, array $b): int {
            $da = $a['days_to_stockout'];
            $db = $b['days_to_stockout'];

            if ($da === null && $db === null) {
                return 0;
            }

            if ($da === null) {
                return 1;
            }

            if ($db === null) {
                return -1;
            }

            return $da <=> $db;
        });

        // Apply limit after sorting
        $alerts = array_slice($alerts, 0, $limit);

        return ['alerts' => $alerts, 'total_below_min' => $totalBelowMin];
    }
}
