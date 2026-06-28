<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class KardexSummaryService
{
    public function __construct(
        private Connection $connection,
        private FifoKardexValuator $fifoValuator,
        private WeightedAverageKardexValuator $weightedAverageValuator,
        private KardexCostSqlHelper $kardexCostSql,
    ) {
    }

    /**
     * @return array{
     *     productId: string,
     *     warehouseId: string|null,
     *     valuationMethod: string,
     *     product: array{
     *         code: string|null,
     *         name: string,
     *         categoryName: string|null,
     *         unitName: string|null,
     *         purchasePrice: string|null
     *     },
     *     currentStock: string,
     *     stockValue: string,
     *     avgUnitCost: string|null,
     *     totalIn: string,
     *     totalOut: string,
     *     movementCount: int,
     *     periodFrom: string|null,
     *     periodTo: string|null,
     *     periodLabel: string
     * }
     *
     * @throws Exception
     */
    public function getSummary(
        string $productId,
        ?string $warehouseId = null,
        ?string $from = null,
        ?string $to = null,
        ?string $valuation = null,
    ): array {
        $product = $this->fetchProduct($productId);
        $movementStats = $this->fetchMovementStats($productId, $warehouseId, $from, $to);
        $valuationMethod = $this->normalizeValuation($valuation);

        $currentStock = $movementStats['currentStock'];

        $movementsForValuation = $this->fetchMovementsForValuation($productId, $warehouseId, $from, $to);

        if ($valuationMethod === 'fifo') {
            $metrics = $this->fifoValuator->endingMetrics($movementsForValuation);
        } else {
            $metrics = $this->weightedAverageValuator->endingMetrics($movementsForValuation);
        }

        $stockValue = $metrics['stockValue'];
        $avgUnitCost = $metrics['avgUnitCost'];

        return [
            'productId' => $productId,
            'warehouseId' => $warehouseId,
            'valuationMethod' => $valuationMethod === 'fifo' ? 'fifo' : 'weighted_purchase_price',
            'product' => $product,
            'currentStock' => $currentStock,
            'stockValue' => $stockValue,
            'avgUnitCost' => $avgUnitCost,
            'totalIn' => $movementStats['totalIn'],
            'totalOut' => $movementStats['totalOut'],
            'movementCount' => $movementStats['movementCount'],
            'periodFrom' => $movementStats['periodFrom'],
            'periodTo' => $movementStats['periodTo'],
            'periodLabel' => $movementStats['periodLabel'],
        ];
    }

    /**
     * @return array{
     *     code: string|null,
     *     name: string,
     *     categoryName: string|null,
     *     unitName: string|null,
     *     purchasePrice: string|null
     * }
     *
     * @throws Exception
     */
    private function fetchProduct(string $productId): array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    p.codigo AS code,
                    p.descripcion AS name,
                    c.descripcion AS category_name,
                    u.name AS unit_name,
                    p.precio_compra AS purchase_price
                FROM producto p
                LEFT JOIN categoria c ON c.categoria_id = p.categoria_id
                LEFT JOIN unidad u ON u.unidad_id = p.unidad_id
                WHERE p.producto_id = :product_id
                SQL,
            ['product_id' => $productId],
        );

        if ($row === false) {
            return [
                'code' => null,
                'name' => '',
                'categoryName' => null,
                'unitName' => null,
                'purchasePrice' => null,
            ];
        }

        $purchasePrice = $row['purchase_price'] ?? null;

        return [
            'code' => $row['code'] !== null ? (string) $row['code'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'categoryName' => $row['category_name'] !== null ? (string) $row['category_name'] : null,
            'unitName' => $row['unit_name'] !== null ? (string) $row['unit_name'] : null,
            'purchasePrice' => $purchasePrice !== null ? number_format((float) $purchasePrice, 2, '.', '') : null,
        ];
    }

    /**
     * @return array{
     *     currentStock: string,
     *     totalIn: string,
     *     totalOut: string,
     *     movementCount: int,
     *     periodFrom: string|null,
     *     periodTo: string|null,
     *     periodLabel: string
     * }
     *
     * @throws Exception
     */
    private function fetchMovementStats(string $productId, ?string $warehouseId, ?string $from, ?string $to): array
    {
        $params = ['product_id' => $productId];
        $where = ['sm.product_id = :product_id'];

        if ($warehouseId !== null && $warehouseId !== '') {
            $where[] = 'sm.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $warehouseId;
        }

        if ($from !== null && $from !== '') {
            $where[] = 'sm.occurred_at >= :date_from';
            $params['date_from'] = $this->normalizeDateBoundary($from, ' 00:00:00');
        }

        if ($to !== null && $to !== '') {
            $where[] = 'sm.occurred_at <= :date_to';
            $params['date_to'] = $this->normalizeDateBoundary($to, ' 23:59:59');
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $aggregate = $this->connection->fetchAssociative(
            <<<SQL
                SELECT
                    COALESCE(SUM(CASE WHEN sm.qty_delta_base > 0 THEN sm.qty_delta_base ELSE 0 END), 0) AS total_in,
                    COALESCE(SUM(CASE WHEN sm.qty_delta_base < 0 THEN ABS(sm.qty_delta_base) ELSE 0 END), 0) AS total_out,
                    COUNT(*) AS movement_count,
                    MIN(sm.occurred_at) AS period_from,
                    MAX(sm.occurred_at) AS period_to
                FROM stock_movements sm
                {$whereClause}
                SQL,
            $params,
        );

        $balanceRow = $this->connection->fetchAssociative(
            <<<SQL
                SELECT inner_q.running_balance
                FROM (
                    SELECT
                        sm.qty_delta_base,
                        SUM(sm.qty_delta_base) OVER (
                            PARTITION BY sm.warehouse_id, sm.product_id
                            ORDER BY sm.occurred_at ASC, sm.created_at ASC
                            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) AS running_balance,
                        sm.occurred_at,
                        sm.created_at
                    FROM stock_movements sm
                    {$whereClause}
                ) AS inner_q
                ORDER BY inner_q.occurred_at DESC, inner_q.created_at DESC
                LIMIT 1
                SQL,
            $params,
        );

        $totalIn = number_format((float) ($aggregate['total_in'] ?? 0), 6, '.', '');
        $totalOut = number_format((float) ($aggregate['total_out'] ?? 0), 6, '.', '');
        $movementCount = (int) ($aggregate['movement_count'] ?? 0);
        $currentStock = number_format((float) ($balanceRow['running_balance'] ?? 0), 6, '.', '');

        $periodFrom = $this->formatPeriodDate($aggregate['period_from'] ?? null);
        $periodTo = $this->formatPeriodDate($aggregate['period_to'] ?? null);

        return [
            'currentStock' => $currentStock,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'movementCount' => $movementCount,
            'periodFrom' => $periodFrom,
            'periodTo' => $periodTo,
            'periodLabel' => $this->buildPeriodLabel($periodFrom, $periodTo, $movementCount),
        ];
    }

    private function normalizeDateBoundary(string $value, string $timeSuffix): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . $timeSuffix;
        }

        return $value;
    }

    private function formatPeriodDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new \DateTimeImmutable((string) $value))->format('Y-m-d');
    }

    private function buildPeriodLabel(?string $from, ?string $to, int $movementCount): string
    {
        if ($movementCount === 0 || $from === null) {
            return 'Sin movimientos';
        }

        if ($to === null || $from === $to) {
            return $this->formatMonthYear($from);
        }

        return sprintf('%s – %s', $this->formatMonthYear($from), $this->formatMonthYear($to));
    }

    private function formatMonthYear(string $isoDate): string
    {
        $date = new \DateTimeImmutable($isoDate);

        return $date->format('M Y');
    }

    /**
     * @return list<array{
     *     warehouse_id: string,
     *     qty_delta: string,
     *     inbound_unit_cost: string|null
     * }>
     *
     * @throws Exception
     */
    private function fetchMovementsForValuation(
        string $productId,
        ?string $warehouseId,
        ?string $from,
        ?string $to,
    ): array {
        $params = ['product_id' => $productId];
        $where = ['sm.product_id = :product_id'];

        if ($warehouseId !== null && $warehouseId !== '') {
            $where[] = 'sm.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $warehouseId;
        }

        if ($from !== null && $from !== '') {
            $where[] = 'sm.occurred_at >= :date_from';
            $params['date_from'] = $this->normalizeDateBoundary($from, ' 00:00:00');
        }

        if ($to !== null && $to !== '') {
            $where[] = 'sm.occurred_at <= :date_to';
            $params['date_to'] = $this->normalizeDateBoundary($to, ' 23:59:59');
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);
        $purchaseItemJoin = $this->kardexCostSql->purchaseItemJoinSql();
        $inboundUnitCost = $this->kardexCostSql->inboundUnitCostExpression();

        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT
                    sm.warehouse_id,
                    sm.qty_delta_base,
                    {$inboundUnitCost} AS inbound_unit_cost
                FROM stock_movements sm
                LEFT JOIN producto p ON p.producto_id = sm.product_id
                LEFT JOIN stock_lots sl ON sl.id = sm.lot_id
                {$purchaseItemJoin}
                {$whereClause}
                ORDER BY sm.occurred_at ASC, sm.created_at ASC
                SQL,
            $params,
        );

        return array_map(static fn (array $row): array => [
            'warehouse_id' => (string) $row['warehouse_id'],
            'qty_delta' => number_format((float) $row['qty_delta_base'], 6, '.', ''),
            'inbound_unit_cost' => $row['inbound_unit_cost'] !== null
                ? number_format((float) $row['inbound_unit_cost'], 4, '.', '')
                : null,
        ], $rows);
    }

    private function normalizeValuation(?string $valuation): string
    {
        return $valuation === 'fifo' ? 'fifo' : 'pp';
    }
}