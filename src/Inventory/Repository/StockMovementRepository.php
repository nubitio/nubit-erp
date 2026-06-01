<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\ApiResource\StockMovement;
use App\Inventory\Core\Enum\StockMovementType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;

/**
 * DBAL-based repository for stock_movements (Kardex).
 *
 * Uses a SQL window function to compute the running balance per
 * (warehouse, product) ordered by occurred_at ASC.
 * Results are then returned in DESC order (most recent first).
 */
final readonly class StockMovementRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Return paginated kardex rows with running_balance.
     *
     * @param array{
     *   warehouse?: string,
     *   product?: string,
     *   type?: string,
     *   from?: string,
     *   to?: string,
     *   sourceId?: string,
     *   sourceIdOperator?: string,
     *   note?: string,
     *   noteOperator?: string,
     * } $filters
     *
     * @return list<StockMovement>
     *
     * @throws Exception
     */
    public function findKardex(array $filters, int $page, int $perPage): array
    {
        $params = [];
        $where  = [];

        if (!empty($filters['warehouse'])) {
            $where[]              = 'sm.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $this->extractUuid($filters['warehouse']);
        }

        if (!empty($filters['product'])) {
            $where[]             = 'sm.product_id = :product_id';
            $params['product_id'] = $this->extractUuid($filters['product']);
        }

        if (!empty($filters['type'])) {
            $where[]        = 'sm.movement_type = :movement_type';
            $params['movement_type'] = $filters['type'];
        }

        if (!empty($filters['from'])) {
            $where[]      = 'sm.occurred_at >= :date_from';
            $params['date_from'] = $this->normalizeDateBoundary($filters['from'], ' 00:00:00');
        }

        if (!empty($filters['to'])) {
            $where[]    = 'sm.occurred_at <= :date_to';
            $params['date_to'] = $this->normalizeDateBoundary($filters['to'], ' 23:59:59');
        }

        if (!empty($filters['sourceId'])) {
            $this->addTextFilter($where, $params, 'sm.source_id', 'source_id', $filters['sourceId'], $filters['sourceIdOperator'] ?? 'contains');
        }

        if (!empty($filters['note'])) {
            $this->addTextFilter($where, $params, 'sm.note', 'note', $filters['note'], $filters['noteOperator'] ?? 'contains');
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        // The inner query computes running_balance in ASC order (chronological).
        // The outer query re-orders DESC (most recent first) for display.
        $sql = <<<SQL
            SELECT
                inner_q.id,
                inner_q.warehouse_id,
                inner_q.warehouse_name,
                inner_q.product_id,
                inner_q.product_name,
                inner_q.product_code,
                inner_q.occurred_at,
                inner_q.movement_type,
                inner_q.qty_delta_base,
                inner_q.qty_original,
                inner_q.unit_id,
                inner_q.unit_name,
                inner_q.source_type,
                inner_q.source_id,
                inner_q.note,
                inner_q.created_at,
                inner_q.running_balance
            FROM (
                SELECT
                    sm.id,
                    sm.warehouse_id,
                    a.descripcion        AS warehouse_name,
                    sm.product_id,
                    p.descripcion        AS product_name,
                    p.codigo             AS product_code,
                    sm.occurred_at,
                    sm.movement_type,
                    sm.qty_delta_base,
                    sm.qty_original,
                    sm.unit_id,
                    u.name               AS unit_name,
                    sm.source_type,
                    sm.source_id,
                    sm.note,
                    sm.created_at,
                    SUM(sm.qty_delta_base) OVER (
                        PARTITION BY sm.warehouse_id, sm.product_id
                        ORDER BY sm.occurred_at ASC, sm.created_at ASC
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) AS running_balance
                FROM stock_movements sm
                LEFT JOIN almacen a  ON a.almacen_id   = sm.warehouse_id
                LEFT JOIN producto p ON p.producto_id  = sm.product_id
                LEFT JOIN unidad u   ON u.unidad_id    = sm.unit_id
                {$whereClause}
            ) AS inner_q
            ORDER BY inner_q.occurred_at DESC, inner_q.created_at DESC
            LIMIT :limit OFFSET :offset
        SQL;

        $params['limit']  = $perPage;
        $params['offset'] = ($page - 1) * $perPage;

        $rows = $this->connection->fetchAllAssociative(
            $sql,
            $params,
            [
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ],
        );

        return array_map($this->hydrateRow(...), $rows);
    }

    /**
     * Count total rows for pagination (without window function overhead).
     *
     * @param array<string, string> $filters
     *
     * @throws Exception
     */
    public function countKardex(array $filters): int
    {
        $params = [];
        $where  = [];

        if (!empty($filters['warehouse'])) {
            $where[]                = 'warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $this->extractUuid($filters['warehouse']);
        }

        if (!empty($filters['product'])) {
            $where[]              = 'product_id = :product_id';
            $params['product_id'] = $this->extractUuid($filters['product']);
        }

        if (!empty($filters['type'])) {
            $where[]                 = 'movement_type = :movement_type';
            $params['movement_type'] = $filters['type'];
        }

        if (!empty($filters['from'])) {
            $where[]              = 'occurred_at >= :date_from';
            $params['date_from']  = $this->normalizeDateBoundary($filters['from'], ' 00:00:00');
        }

        if (!empty($filters['to'])) {
            $where[]            = 'occurred_at <= :date_to';
            $params['date_to']  = $this->normalizeDateBoundary($filters['to'], ' 23:59:59');
        }

        if (!empty($filters['sourceId'])) {
            $this->addTextFilter($where, $params, 'source_id', 'source_id', $filters['sourceId'], $filters['sourceIdOperator'] ?? 'contains');
        }

        if (!empty($filters['note'])) {
            $this->addTextFilter($where, $params, 'note', 'note', $filters['note'], $filters['noteOperator'] ?? 'contains');
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM stock_movements {$whereClause}",
            $params,
        );

        return (int) ($count ?: 0);
    }

    // ─── Hydration ───────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row): StockMovement
    {
        $sm = new StockMovement();

        $sm->id           = (string) $row['id'];
        $sm->occurredAt   = $row['occurred_at'] ? (new \DateTimeImmutable((string) $row['occurred_at']))->format(\DateTimeInterface::ATOM) : null;
        $sm->createdAt    = $row['created_at'] ? (new \DateTimeImmutable((string) $row['created_at']))->format(\DateTimeInterface::ATOM) : null;
        $sm->qtyDeltaBase = isset($row['qty_delta_base']) ? number_format((float) $row['qty_delta_base'], 6, '.', '') : null;
        $sm->runningBalance = isset($row['running_balance']) ? number_format((float) $row['running_balance'], 6, '.', '') : null;
        $sm->sourceType   = $row['source_type'] ?? null;
        $sm->sourceId     = $row['source_id']   ?? null;
        $sm->note         = $row['note']         ?? null;

        // qtyIn / qtyOut derived from delta sign
        $delta = (float) ($row['qty_delta_base'] ?? 0);
        if ($delta > 0) {
            $sm->qtyIn  = number_format($delta, 6, '.', '');
            $sm->qtyOut = null;
        } elseif ($delta < 0) {
            $sm->qtyIn  = null;
            $sm->qtyOut = number_format(abs($delta), 6, '.', '');
        }

        // movementType + label
        $typeValue = (string) ($row['movement_type'] ?? '');
        $sm->movementType = $typeValue;
        $typeEnum = StockMovementType::tryFrom($typeValue);
        $sm->movementTypeLabel = $typeEnum?->label() ?? $typeValue;

        // Warehouse
        if (!empty($row['warehouse_id'])) {
            $sm->warehouse = [
                'id'   => $row['warehouse_id'],
                'name' => $row['warehouse_name'] ?? '',
            ];
        }

        // Product
        if (!empty($row['product_id'])) {
            $sm->product = [
                'id'   => $row['product_id'],
                'name' => $row['product_name'] ?? '',
                'code' => $row['product_code'] ?? null,
            ];
        }

        // Unit
        if (!empty($row['unit_id'])) {
            $sm->unit = [
                'id'   => (int) $row['unit_id'],
                'name' => $row['unit_name'] ?? '',
            ];
        }

        return $sm;
    }

    /**
     * Extract bare UUID from an IRI like /api/warehouses/uuid or plain uuid.
     */
    private function extractUuid(string $value): string
    {
        return basename($value);
    }

    private function normalizeDateBoundary(string $value, string $timeSuffix): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . $timeSuffix;
        }

        return $value;
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     */
    private function addTextFilter(array &$where, array &$params, string $column, string $param, string $value, string $operator): void
    {
        switch ($operator) {
            case '=':
                $where[] = sprintf('%s = :%s', $column, $param);
                $params[$param] = $value;
                break;
            case '<>':
                $where[] = sprintf('%s <> :%s', $column, $param);
                $params[$param] = $value;
                break;
            case 'notcontains':
                $where[] = sprintf('%s NOT LIKE :%s', $column, $param);
                $params[$param] = '%' . $value . '%';
                break;
            case 'startswith':
                $where[] = sprintf('%s LIKE :%s', $column, $param);
                $params[$param] = $value . '%';
                break;
            case 'endswith':
                $where[] = sprintf('%s LIKE :%s', $column, $param);
                $params[$param] = '%' . $value;
                break;
            default:
                $where[] = sprintf('%s LIKE :%s', $column, $param);
                $params[$param] = '%' . $value . '%';
                break;
        }
    }
}
