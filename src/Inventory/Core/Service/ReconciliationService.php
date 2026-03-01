<?php

declare(strict_types=1);

namespace App\Inventory\Core\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Reconciles the ledger (stock_movements) against the materialized read model (stock_items).
 *
 * Invariant: SUM(stock_movements.qty_delta_base) == stock_items.qty_on_hand
 * Tolerance: abs(diff) <= 0.000001
 *
 * Writes results to inventory_reconciliation_runs for audit and alerting.
 */
final readonly class ReconciliationService
{
    private const TOLERANCE = '0.000001';

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Run reconciliation for all (warehouse, product) pairs in stock_items.
     * Optionally filter by warehouseId or productId.
     *
     * @return array{ok: int, drift: int, error: int}
     *
     * @throws \Doctrine\DBAL\Exception
     */
    /**
     * @return array{ok: int, drift: int, error: int}
     */
    public function run(?string $warehouseId = null, ?string $productId = null): array
    {
        /** @var array{ok: int, drift: int, error: int} $results */
        $results = ['ok' => 0, 'drift' => 0, 'error' => 0];

        $rows = $this->fetchStockItemPairs($warehouseId, $productId);

        foreach ($rows as $row) {
            try {
                $status = $this->reconcileOne(
                    $row['warehouse_id'],
                    $row['product_id'],
                    $row['qty_on_hand']
                );
                ++$results[$status];
            } catch (\Throwable $e) {
                ++$results['error'];
                $this->logger->error('Reconciliation error', [
                    'warehouse_id' => $row['warehouse_id'],
                    'product_id'   => $row['product_id'],
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        /** @var array{ok: int, drift: int, error: int} */
        return $results;
    }

    /**
     * Reconcile a single (warehouse, product) pair.
     * Returns 'ok', 'drift', or 'error'.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function reconcileOne(string $warehouseId, string $productId, string $stockItemsQty): string
    {
        $ledgerSum = $this->connection->fetchOne(
            <<<'SQL'
            SELECT COALESCE(SUM(qty_delta_base), '0.000000')
            FROM stock_movements
            WHERE warehouse_id = :w AND product_id = :p
            SQL,
            ['w' => $warehouseId, 'p' => $productId]
        );

        $ledgerSum = (string) ($ledgerSum ?: '0');
        $safeStockQty = is_numeric($stockItemsQty) ? $stockItemsQty : '0';
        $numericLedger = is_numeric($ledgerSum) ? $ledgerSum : '0';
        $diff = bcsub($numericLedger, $safeStockQty, 6); // phpcs:ignore
        $absDiff = ltrim($diff, '-');

        $status = bccomp($absDiff, self::TOLERANCE, 6) > 0 ? 'DRIFT' : 'OK';

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO inventory_reconciliation_runs
                (id, warehouse_id, product_id, ledger_sum, stock_items_qty, diff, status, run_at)
            VALUES
                (:id, :w, :p, :ledger_sum, :stock_items_qty, :diff, :status, :run_at)
            SQL,
            [
                'id'             => Uuid::v7()->toRfc4122(),
                'w'              => $warehouseId,
                'p'              => $productId,
                'ledger_sum'     => $ledgerSum,
                'stock_items_qty' => $stockItemsQty,
                'diff'           => $diff,
                'status'         => $status,
                'run_at'         => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]
        );

        if ($status === 'DRIFT') {
            $this->logger->warning('Inventory drift detected', [
                'warehouse_id'    => $warehouseId,
                'product_id'      => $productId,
                'ledger_sum'      => $ledgerSum,
                'stock_items_qty' => $stockItemsQty,
                'diff'            => $diff,
            ]);
        }

        return strtolower($status);
    }

    /**
     * @return list<array{warehouse_id: string, product_id: string, qty_on_hand: string}>
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function fetchStockItemPairs(?string $warehouseId, ?string $productId): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('warehouse_id', 'product_id', 'qty_on_hand')
            ->from('stock_items');

        if ($warehouseId !== null) {
            $qb->andWhere('warehouse_id = :w')->setParameter('w', $warehouseId);
        }
        if ($productId !== null) {
            $qb->andWhere('product_id = :p')->setParameter('p', $productId);
        }

        /** @var list<array{warehouse_id: string, product_id: string, qty_on_hand: string}> */
        return $qb->executeQuery()->fetchAllAssociative();
    }
}
